#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
base_href="${1:-/pwa/}"
archive_name="${2:-Aabhushan-ERP-PWA-1.0.2-build3.zip}"

if [[ "$base_href" != /*/ ]]; then
  echo "Base href must start and end with / (example: /pwa/)." >&2
  exit 1
fi

cd "$project_dir"
flutter build web --release --base-href "$base_href"
cp web/.htaccess build/web/.htaccess

cd build/web
zip -qr -FS "../$archive_name" . -x .last_build_id

echo "$project_dir/build/$archive_name"
