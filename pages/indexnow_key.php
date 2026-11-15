<?php
if (!hash_equals(indexnow_key(), (string) param('key'))) abort(404);
header('Content-Type: text/plain; charset=utf-8');
echo indexnow_key();
