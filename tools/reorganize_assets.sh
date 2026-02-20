#!/bin/zsh
set -euo pipefail
setopt null_glob

repo_dir="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_dir"

mkdir -p json kotob update

[[ -f books_metadata.json ]] && git mv books_metadata.json json/books_metadata.json
[[ -f content_audio_metadata.json ]] && git mv content_audio_metadata.json json/content_audio_metadata.json
[[ -f structure_metadata.json ]] && git mv structure_metadata.json json/structure_metadata.json
[[ -f update.json ]] && git mv update.json update/update.json

for file in *.sql *.sql.gz *.db; do
  [[ -f "$file" ]] && git mv "$file" kotob/
done

if [[ -f json/books_metadata.json ]]; then
  perl -i -pe 's#(https://raw\.githubusercontent\.com/kerim317gh/kdini/refs/heads/main/)(?!kotob/)([^"\s]+\.(?:sql|sql\.gz|db))#$1kotob/$2#g' json/books_metadata.json
fi

echo "Reorganization finished."
git status --short
