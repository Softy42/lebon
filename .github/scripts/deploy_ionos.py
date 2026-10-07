"""Incremental SFTP publication with private server backups and rollback."""

import base64
import errno
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import socket
import stat
import subprocess
import shutil
import tempfile
import uuid


REMOTE_ROOT = "/clickandbuilds/MaisonMelina"
BACKUP_ROOT = "/.maison-melina-deploy"
INITIAL_REVISION = "9c4dd96363d4e12fbe9fb3085d8583da2af84c18"
# Official IONOS webhosting fingerprints, checked 2026-10-06:
# https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/uebersicht-der-ssh-fingerabdruecke-im-ionos-webhosting/
HOST_FINGERPRINTS = {
    "SHA256:J4oM+B2g7zZWAI3DolXR1e4vdIMrGO301kEN14/slsQ",
    "SHA256:psLDE8kfhoS9GWrc/GTrdlPvyKPcWcGZv2JxOCvwF3w",
    "SHA256:1gx2w8Rtv3wCgi7Jh8myf/KVd72cRQbow03UP8P095Q",
}
PUBLIC_EXTENSIONS = {
    ".html", ".css", ".js", ".json", ".xml", ".txt", ".ico",
    ".jpg", ".jpeg", ".png", ".webp", ".svg", ".gif", ".avif",
    ".woff", ".woff2", ".ttf", ".mp4", ".mov", ".avi", ".pdf",
}


def public_path(name):
    path = PurePosixPath(name)
    if path.is_absolute() or ".." in path.parts or "\\" in name:
        return False
    if name == ".htaccess":
        return True
    if any(part.startswith(".") for part in path.parts):
        return False
    return path.suffix.lower() in PUBLIC_EXTENSIONS


def collect_files(root, revision):
    if not re.fullmatch(r"[0-9a-f]{40}", revision):
        raise RuntimeError("Revision de reference invalide.")
    names = subprocess.check_output(
        ["git", "diff", "--name-only", "-z", "--diff-filter=AMRT", revision, "HEAD", "--"], cwd=root
    ).decode("utf-8").split("\0")
    files = []
    for name in names:
        if not public_path(name):
            continue
        local = root / name
        if local.is_symlink() or not local.is_file() or not local.resolve().is_relative_to(root.resolve()):
            raise RuntimeError(f"Fichier local invalide : {name}")
        with local.open("rb") as stream:
            if stream.read(100).startswith(b"version https://git-lfs.github.com/spec/v1"):
                raise RuntimeError(f"Contenu Git LFS manquant : {name}")
        files.append(name)
    # Publish assets first, then HTML so newly referenced files already exist.
    return sorted(files, key=lambda name: (name.endswith(".html"), name))


def verify_host_key(key):
    digest = base64.b64encode(hashlib.sha256(key.asbytes()).digest()).decode().rstrip("=")
    if "SHA256:" + digest not in HOST_FINGERPRINTS:
        raise RuntimeError("Cle du serveur non reconnue. Verifier les empreintes officielles IONOS avant de continuer.")


def check_destination(sftp):
    if not stat.S_ISDIR(sftp.lstat(REMOTE_ROOT).st_mode):
        raise RuntimeError("Le dossier MaisonMelina n'est pas un repertoire ordinaire.")
    sftp.chdir(REMOTE_ROOT)
    if not stat.S_ISREG(sftp.lstat("index.html").st_mode):
        raise RuntimeError("La page d'accueil distante est absente ou n'est pas un fichier ordinaire.")
    with sftp.open("index.html", "rb") as stream:
        if b"maison-melina.fr" not in stream.read(16384):
            raise RuntimeError("Le dossier distant ne semble pas contenir le site Maison Melina.")


def attributes(sftp, path):
    try:
        return sftp.lstat(path)
    except OSError as error:
        if error.errno == errno.ENOENT:
            return None
        raise


def directory(sftp, path, create=False, mode=0o755):
    info = attributes(sftp, path)
    if info is None and create:
        sftp.mkdir(path, mode=mode)
        info = sftp.lstat(path)
    if info is not None and not stat.S_ISDIR(info.st_mode):
        raise RuntimeError(f"Repertoire distant invalide : {path}")
    return info is not None


def parents(sftp, name, create=False):
    for parent in reversed(PurePosixPath(name).parents):
        if str(parent) != ".":
            directory(sftp, str(parent), create=create)


def stream_hash(stream):
    digest = hashlib.sha256()
    for chunk in iter(lambda: stream.read(1024 * 1024), b""):
        digest.update(chunk)
    return digest.hexdigest()


def remote_hash(sftp, name):
    parents(sftp, name)
    info = attributes(sftp, name)
    if info is None:
        return None
    if not stat.S_ISREG(info.st_mode):
        raise RuntimeError(f"Fichier distant invalide : {name}")
    with sftp.open(name, "rb") as stream:
        return stream_hash(stream)


def replace_file(sftp, local, destination, mode=0o644):
    path = PurePosixPath(destination)
    temporary = str(path.with_name(".ionos-" + uuid.uuid4().hex + ".tmp"))
    try:
        sftp.put(str(local), temporary, confirm=True)
        sftp.chmod(temporary, mode)
        sftp.posix_rename(temporary, destination)
    except Exception:
        try:
            sftp.remove(temporary)
        except OSError:
            pass
        raise


def read_json(sftp, path, default=None):
    info = attributes(sftp, path)
    if info is None:
        return default
    if not stat.S_ISREG(info.st_mode):
        raise RuntimeError("Metadonnees distantes invalides.")
    with sftp.open(path, "rb") as stream:
        return json.loads(stream.read().decode("utf-8"))


def write_json(sftp, path, data):
    with tempfile.TemporaryDirectory() as temporary:
        local = Path(temporary) / "metadata.json"
        local.write_text(json.dumps(data, ensure_ascii=False), encoding="utf-8")
        replace_file(sftp, local, path, 0o600)


def read_state(sftp):
    directory(sftp, BACKUP_ROOT)
    state = read_json(sftp, BACKUP_ROOT + "/state.json", {"revision": INITIAL_REVISION, "latest": None, "pending": None})
    if not isinstance(state, dict) or not re.fullmatch(r"[0-9a-f]{40}", state.get("revision", "")):
        raise RuntimeError("Etat de publication invalide.")
    return state


def plan(sftp, root, files):
    changes = []
    for name in files:
        before = remote_hash(sftp, name)
        with (root / name).open("rb") as stream:
            after = stream_hash(stream)
        if before == after:
            continue
        info = attributes(sftp, name)
        changes.append({"path": name, "before": before, "after": after,
                        "mode": stat.S_IMODE(info.st_mode) if info else 0o644})
        print(("Nouveau : " if before is None else "Modifie : ") + name, flush=True)
    return changes


def publish(sftp, root, changes, state, revision):
    if state.get("pending"):
        raise RuntimeError("Publication interrompue : choisir restaurer avant une nouvelle publication.")
    if not changes:
        return "Aucun fichier different a transferer."
    directory(sftp, BACKUP_ROOT, create=True, mode=0o700)
    backup_id = uuid.uuid4().hex
    backup = BACKUP_ROOT + "/" + backup_id
    sftp.mkdir(backup, mode=0o700)
    manifest = {"previous_state": state, "revision": revision, "files": changes}
    # Save ALL old and new contents before modifying any live file. The new
    # copies also make files removed by rollback recoverable.
    with tempfile.TemporaryDirectory() as temporary:
        for index, change in enumerate(changes):
            name = change["path"]
            if change["before"] is not None:
                local = Path(temporary) / str(index)
                with sftp.open(name, "rb") as source, local.open("wb") as target:
                    shutil.copyfileobj(source, target)
                with local.open("rb") as stream:
                    if stream_hash(stream) != change["before"]:
                        raise RuntimeError(f"Le fichier a change pendant la preparation : {name}")
                replace_file(sftp, local, f"{backup}/{index}.before", 0o600)
            replace_file(sftp, root / name, f"{backup}/{index}.after", 0o600)
    write_json(sftp, backup + "/manifest.json", manifest)
    write_json(sftp, BACKUP_ROOT + "/state.json", dict(state, pending=backup_id))
    # Preflight all paths before starting the transfer.
    for change in changes:
        if remote_hash(sftp, change["path"]) != change["before"]:
            raise RuntimeError("Un fichier distant a change. Publication arretee ; sauvegarde disponible.")
    for change in changes:
        name = change["path"]
        parents(sftp, name, create=True)
        replace_file(sftp, root / name, name, change["mode"])
        print("Publie : " + name, flush=True)
    write_json(sftp, BACKUP_ROOT + "/state.json", {"revision": revision, "latest": backup_id, "pending": None})
    return f"{len(changes)} fichiers publies. Sauvegarde : {backup_id}. Retour possible avec restaurer."


def restore(sftp, state):
    backup_id = state.get("pending") or state.get("latest")
    if not backup_id:
        return "Aucune publication a restaurer."
    if not re.fullmatch(r"[0-9a-f]{32}", backup_id):
        raise RuntimeError("Identifiant de sauvegarde invalide.")
    backup = BACKUP_ROOT + "/" + backup_id
    directory(sftp, backup)
    manifest = read_json(sftp, backup + "/manifest.json")
    if not manifest or not isinstance(manifest.get("files"), list):
        raise RuntimeError("Sauvegarde incomplete.")
    # Check every destination and backup before restoring any file. Do not
    # overwrite subsequent manual edits made with FileZilla.
    for index, change in enumerate(manifest["files"]):
        name = change["path"]
        if not public_path(name):
            raise RuntimeError("Chemin de restauration invalide.")
        if remote_hash(sftp, name) not in (change["before"], change["after"]):
            raise RuntimeError(f"Modification ulterieure detectee : {name}. Restauration arretee.")
        if change["before"] is not None and remote_hash(sftp, f"{backup}/{index}.before") != change["before"]:
            raise RuntimeError("Sauvegarde endommagee. Aucun fichier restaure.")
        if remote_hash(sftp, f"{backup}/{index}.after") != change["after"]:
            raise RuntimeError("Sauvegarde de la nouvelle version endommagee.")
    with tempfile.TemporaryDirectory() as temporary:
        # Restore HTML before removing assets added by the publication.
        ordered = sorted(enumerate(manifest["files"]), key=lambda pair: (not pair[1]["path"].endswith(".html"), pair[1]["path"]))
        for index, change in ordered:
            name = change["path"]
            if remote_hash(sftp, name) == change["before"]:
                continue
            if change["before"] is None:
                sftp.remove(name)
            else:
                local = Path(temporary) / str(index)
                with sftp.open(f"{backup}/{index}.before", "rb") as source, local.open("wb") as target:
                    shutil.copyfileobj(source, target)
                replace_file(sftp, local, name, change["mode"])
            print("Restaure : " + name, flush=True)
    write_json(sftp, BACKUP_ROOT + "/state.json", manifest["previous_state"])
    return "Version precedente restauree. La sauvegarde reste conservee sur IONOS."


def main():
    root = Path(__file__).resolve().parents[2]
    names = ("IONOS_SFTP_HOST", "IONOS_SFTP_USER", "IONOS_SFTP_PASSWORD")
    missing = [name for name in names if not os.environ.get(name)]
    if missing:
        raise RuntimeError("Secrets GitHub manquants : " + ", ".join(missing))
    host, user, password = (os.environ[name] for name in names)
    if not re.fullmatch(r"access[0-9]+\.webspace-data\.io", host):
        raise RuntimeError("L'hote doit etre l'adresse access...webspace-data.io indiquee par IONOS.")
    if not re.fullmatch(r"u[0-9]+", user):
        raise RuntimeError("Verifier le nom d'utilisateur SFTP IONOS.")
    action = os.environ.get("DEPLOY_ACTION", "verifier")
    if action not in ("verifier", "publier", "restaurer"):
        raise RuntimeError("Mode de publication invalide.")

    import paramiko

    with socket.create_connection((host, 22), timeout=30) as connection:
        with paramiko.Transport(connection) as transport:
            transport.banner_timeout = 30
            transport.auth_timeout = 30
            transport.start_client(timeout=30)
            # Verify the server identity before transmitting the password.
            verify_host_key(transport.get_remote_server_key())
            transport.auth_password(user, password)
            transport.set_keepalive(30)
            with paramiko.SFTPClient.from_transport(transport) as sftp:
                sftp.get_channel().settimeout(120)
                check_destination(sftp)
                state = read_state(sftp)
                if action == "restaurer":
                    message = restore(sftp, state)
                else:
                    if state.get("pending"):
                        raise RuntimeError("Publication interrompue : choisir restaurer avant de poursuivre.")
                    files = collect_files(root, state["revision"])
                    changes = plan(sftp, root, files)
                    if action == "publier":
                        revision = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=root).decode().strip()
                        message = publish(sftp, root, changes, state, revision)
                    else:
                        message = f"Connexion verifiee : {len(changes)} fichiers nouveaux ou modifies a publier. Aucun fichier distant modifie."
                print(message)
                if os.environ.get("GITHUB_STEP_SUMMARY"):
                    with open(os.environ["GITHUB_STEP_SUMMARY"], "a", encoding="utf-8") as summary:
                        summary.write(message + "\n")


if __name__ == "__main__":
    main()
