#!/bin/bash
# Script helper — Tim: Backend
# Membuat semua public entry points yang tipis (hanya bootstrap + require view)

BASE=/home/claude/lordik

make_entry() {
  local file="$1"
  local view="$2"
  mkdir -p "$(dirname $BASE/public/$file)"
  cat > "$BASE/public/$file" << PHPEOF
<?php
// public/$file — Tim: Backend (entry point tipis)
require_once __DIR__ . '$(echo $view | sed "s|[^/]||g" | sed "s|/|/../|g" | head -c $(($(echo $view | tr -cd '/' | wc -c) * 4 - 1 )))/../core/bootstrap.php';
require_once BASE_PATH . '/app/Views/$view.php';
PHPEOF
}
