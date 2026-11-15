<?php
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /seeker\nDisallow: /employer\nDisallow: /account\nDisallow: /api/\nDisallow: /file/\nDisallow: /notifications\nDisallow: /*/apply\n\nSitemap: " . url('sitemap.xml') . "\n";
