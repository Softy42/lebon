"""Publish tracked website files over SFTP; no remote files are deleted."""

import base64
import errno
import hashlib
import os
from pathlib import Path, PurePosixPath
import re
import socket
import stat
import subprocess
import uuid


REMOTE_ROOT = "/clickandbuilds/MaisonMelina"
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


def collect_files(root):
    names = subprocess.check_output(
        ["git", "ls-files", "-z"], cwd=root
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
    if "index.html" not in files:
        raise RuntimeError("La page d'accueil manque dans les fichiers a publier.")
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


def publish(sftp, root, files):
    directories = sorted(
        {str(parent) for name in files for parent in PurePosixPath(name).parents if str(parent) != "."},
        key=lambda name: (name.count("/"), name),
    )
    for directory in directories:
        try:
            attributes = sftp.lstat(directory)
        except OSError as error:
            if error.errno != errno.ENOENT:
                raise
            sftp.mkdir(directory)
            attributes = sftp.lstat(directory)
        if not stat.S_ISDIR(attributes.st_mode):
            raise RuntimeError(f"Repertoire distant invalide : {directory}")
    for name in files:
        destination = PurePosixPath(name)
        temporary = str(destination.with_name(".ionos-" + uuid.uuid4().hex + ".tmp"))
        try:
            # Upload to a temporary name; replace the live file only after success.
            sftp.put(str(root / name), temporary, confirm=True)
            sftp.chmod(temporary, 0o644)
            sftp.posix_rename(temporary, name)
        except Exception:
            try:
                sftp.remove(temporary)
            except OSError:
                pass
            raise
        print(f"Publie : {name}", flush=True)


def main():
    root = Path(__file__).resolve().parents[2]
    files = collect_files(root)
    names = ("IONOS_SFTP_HOST", "IONOS_SFTP_USER", "IONOS_SFTP_PASSWORD")
    missing = [name for name in names if not os.environ.get(name)]
    if missing:
        raise RuntimeError("Secrets GitHub manquants : " + ", ".join(missing))
    host, user, password = (os.environ[name] for name in names)
    if not re.fullmatch(r"access[0-9]+\.webspace-data\.io", host):
        raise RuntimeError("L'hote doit etre l'adresse access...webspace-data.io indiquee par IONOS.")
    if not re.fullmatch(r"u[0-9]+", user):
        raise RuntimeError("Verifier le nom d'utilisateur SFTP IONOS.")
    if os.environ.get("PUBLISH", "false") not in ("true", "false"):
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
                print(f"Connexion verifiee. Destination : {REMOTE_ROOT}. {len(files)} fichiers prepares.")
                if os.environ.get("PUBLISH") == "true":
                    publish(sftp, root, files)
                    message = f"Publication terminee : {len(files)} fichiers transferes vers IONOS."
                else:
                    message = "Connexion verifiee. Aucun fichier distant modifie. Relancer en cochant Publier pour mettre le site en ligne."
                print(message)
                if os.environ.get("GITHUB_STEP_SUMMARY"):
                    with open(os.environ["GITHUB_STEP_SUMMARY"], "a", encoding="utf-8") as summary:
                        summary.write(message + "\n")


if __name__ == "__main__":
    main()
