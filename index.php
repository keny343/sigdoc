<?php
// Prefer static landing; keep file for hosts that default to index.php
header('Location: /index.html', true, 302);
exit;
