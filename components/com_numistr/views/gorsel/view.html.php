<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;

class NumistrViewGorsel extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $id  = $app->input->getInt('id', 0);
        $wmOverride = $app->input->getInt('wm', -1); // -1: no override, 0: off, 1: on

        if (!$id) {
            header('HTTP/1.1 400 Bad Request');
            exit('ID gerekli');
        }

        // Çıktı tamponlarını kapat (üst katman sıkıştırmaları dâhil)
        @ini_set('zlib.output_compression', 0);
        while (ob_get_level() > 0) { @ob_end_clean(); }

        // ---- PROBE (isteğe bağlı test): &probe=1 ile 1x1 GIF + özel header döner ----
        if ($app->input->getInt('probe', 0) === 1) {
            header('X-Numistr-Probe: yes');
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Content-Type: image/gif');
            echo base64_decode('R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='); // 1x1 gif
            exit;
        }
        // ---------------------------------------------------------------------------

        // Parametreler
        $params         = ComponentHelper::getParams('com_numistr');
        $imagesRoot     = rtrim($params->get('images_path', '../sikke'), '/');
        $regionMapJson  = (string) $params->get('region_map_json', '');
        $useWatermark   = (int) $params->get('watermark_enable', 1);
        $wmText         = (string) $params->get('watermark_text', 'Numistr');
        $wmPos          = (string) $params->get('watermark_position', 'bottom-right');
        $wmOpacityPct   = (int) $params->get('watermark_opacity', 60);
        $wmOpacityPct   = max(0, min(100, $wmOpacityPct));
        $wmDiagonal     = (int) $params->get('watermark_diagonal', 1); // 1: diagonal, 0: düz

        // URL override
        if ($wmOverride === 0) $useWatermark = 0;
        if ($wmOverride === 1) $useWatermark = 1;

        // Bölge eşlemesi (kategori alias -> klasör adı)
        $regionMap = [];
        if ($regionMapJson) {
            try {
                $decoded = json_decode($regionMapJson, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) $regionMap = $decoded;
            } catch (\Throwable $e) { /* ignore */ }
        }

        // DB'den görsel bilgisi
        $db = Factory::getDbo();
        $q  = $db->getQuery(true)
            ->select($db->quoteName(['filename', 'coin_id']))
            ->from($db->quoteName('coins_images'))
            ->where($db->quoteName('image_id') . ' = ' . (int) $id)
            ->setLimit(1);
        $db->setQuery($q);
        $img = $db->loadObject();

        if (!$img || empty($img->filename)) {
            header('HTTP/1.1 404 Not Found');
            exit('Görsel bulunamadı');
        }

        $relative = ltrim($img->filename, '/');

        // Dosya adı alt klasör içermiyorsa: kategoriden bölge klasörü belirle
        if (strpos($relative, '/') === false) {
            $catIdQ = $db->getQuery(true)
                ->select($db->quoteName('catid'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . (int) $img->coin_id)
                ->setLimit(1);
            $db->setQuery($catIdQ);
            $catid = (int) $db->loadResult();

            $regionAlias = '';
            if ($catid) {
                $aliasQ = $db->getQuery(true)
                    ->select($db->quoteName('alias'))
                    ->from($db->quoteName('#__categories'))
                    ->where($db->quoteName('id') . ' = ' . $catid)
                    ->setLimit(1);
                $db->setQuery($aliasQ);
                $regionAlias = (string) $db->loadResult();
            }

            $key = strtolower(trim($regionAlias));
            if ($key !== '' && isset($regionMap[$key]) && is_string($regionMap[$key]) && $regionMap[$key] !== '') {
                $folder = trim($regionMap[$key], '/');
            } else {
                $folder = $key;
            }

            if ($folder !== '') {
                $relative = $folder . '/' . $relative;
            }
        }

        // Güvenli gerçek yol üret
        $basePath = realpath(JPATH_ROOT . '/' . trim($imagesRoot, '/'));
        $filePath = $basePath ? realpath($basePath . '/' . $relative) : false;

        if (!$basePath || !$filePath || strpos($filePath, $basePath) !== 0 || !is_file($filePath)) {
            header('HTTP/1.1 404 Not Found');
            exit('Dosya bulunamadı');
        }

        // MIME/uzantı
        $mime = 'image/jpeg';
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($filePath);
            if ($m) $mime = $m;
        }
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // ---- WM Debug (isteğe bağlı): &wmdbg=1 ile temel info döndür ----
        if ($app->input->getInt('wmdbg', 0) === 1) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "id=" . $id . "\n";
            echo "images_path=" . $params->get('images_path') . "\n";
            echo "mime=" . $mime . "\n";
            echo "ext=" . $ext . "\n";
            echo "useWatermark=" . $useWatermark . " (override=" . $wmOverride . ")\n";
            echo "gd_loaded=" . (int) extension_loaded('gd') . "\n";
            echo "funcs jpg:" . (int) function_exists('imagecreatefromjpeg')
                 . " png:" . (int) function_exists('imagecreatefrompng')
                 . " gif:" . (int) function_exists('imagecreatefromgif')
                 . " webp:" . (int) function_exists('imagecreatefromwebp') . "\n";
            exit;
        }
        // ----------------------------------------------------------------

        // Watermark kapalıysa ham dosya gönder
        if (!$useWatermark) {
            header_remove('Cache-Control');
            header_remove('Expires');
            header('X-Numistr-WM: off');
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');

            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($filePath));
            header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($filePath);
            exit;
        }

        // Dosyayı GD ile aç (WebP/PNG/GIF/JPEG)
        $src = null;
        if ($mime === 'image/webp' || $ext === 'webp') {
            if (function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($filePath);
        } elseif ($mime === 'image/png' || $ext === 'png') {
            if (function_exists('imagecreatefrompng')) $src = @imagecreatefrompng($filePath);
        } elseif ($mime === 'image/gif' || $ext === 'gif') {
            if (function_exists('imagecreatefromgif')) $src = @imagecreatefromgif($filePath);
        } else {
            if (function_exists('imagecreatefromjpeg')) $src = @imagecreatefromjpeg($filePath);
        }

        if (!$src) {
            // GD açılamadı → ham dosya
            header_remove('Cache-Control');
            header_remove('Expires');
            header('X-Numistr-WM: off');
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');

            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($filePath));
            header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($filePath);
            exit;
        }

        // Truecolor hedef + arka plan beyaz (PNG/GIF şeffaflık için)
        $sw = imagesx($src);
        $sh = imagesy($src);
        $image = imagecreatetruecolor($sw, $sh);
        imagealphablending($image, true);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $sw, $sh, $white);
        imagecopy($image, $src, 0, 0, 0, 0, $sw, $sh);
        imagedestroy($src);

        // ---- GEÇİCİ GÖRSEL TEST (URL'ye &wmtest=1 ekleyince çok belirgin damga bas) ----
        if ($app->input->getInt('wmtest', 0) === 1) {
            $bandHeight = max( (int)round($sh * 0.18), 80 );
            $bandY1 = (int)(($sh - $bandHeight) / 2);
            $bandY2 = $bandY1 + $bandHeight;
            $bandCol = imagecolorallocatealpha($image, 0, 0, 0, 40);
            imagefilledrectangle($image, 0, $bandY1, $sw, $bandY2, $bandCol);

            $lineCol = imagecolorallocatealpha($image, 255, 255, 255, 80);
            imageline($image, 0, 0, $sw, $sh, $lineCol);
            imageline($image, 0, $sh, $sw, 0, $lineCol);

            $bigText = 'NUMISTR.ORG';
            $fontSizeBig = max(28, (int)round($sw * 0.065));
            $ttfOk = false;
            $fontCandidatesTest = [
                JPATH_ROOT . '/media/fonts/DejaVuSans.ttf',
                JPATH_ROOT . '/media/fonts/OpenSans.ttf',
                JPATH_ROOT . '/media/fonts/ARIALN.TTF',
                JPATH_ROOT . '/media/fonts/ARIALN.TFF',
                JPATH_ROOT . '/media/fonts/arial.ttf',
                JPATH_ROOT . '/media/fonts/verdana.ttf',
            ];
            foreach ($fontCandidatesTest as $fc) { if (is_file($fc)) { $ttf = $fc; $ttfOk = true; break; } }

            if ($ttfOk && function_exists('imagettfbbox')) {
                $bbox  = imagettfbbox($fontSizeBig, 0, $ttf, $bigText);
                $textW = abs($bbox[2] - $bbox[0]);
                $textH = abs($bbox[7] - $bbox[1]);
                $x = (int)(($sw - $textW) / 2);
                $y = (int)(($sh + $textH) / 2);

                $shadow = imagecolorallocatealpha($image, 0, 0, 0, 80);
                imagettftext($image, $fontSizeBig, 0, $x+3, $y+3, $shadow, $ttf, $bigText);

                $txtCol = imagecolorallocatealpha($image, 255, 255, 255, 30);
                imagettftext($image, $fontSizeBig, 0, $x, $y, $txtCol, $ttf, $bigText);
            } else {
                $font = 5;
                $txtW = imagefontwidth($font) * strlen($bigText);
                $txtH = imagefontheight($font);
                $x = (int)(($sw - $txtW) / 2);
                $y = (int)(($sh - $txtH) / 2);
                $bg = imagecolorallocatealpha($image, 0, 0, 0, 80);
                imagefilledrectangle($image, $x-6, $y-6, $x+$txtW+6, $y+$txtH+6, $bg);
                $fg = imagecolorallocatealpha($image, 255, 255, 255, 30);
                imagestring($image, $font, $x, $y, $bigText, $fg);
            }

            header_remove('Cache-Control');
            header_remove('Expires');
            header('X-Numistr-WM: test');
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Content-Type: image/jpeg');
            header('X-Content-Type-Options: nosniff');
            imagejpeg($image, null, 90);
            imagedestroy($image);
            exit;
        }
        // ---- /GEÇİCİ TEST ----

        // Watermark parametreleri
        $text     = ($wmText !== '' ? $wmText : 'Numistr');
        $fontSize = max(28, (int)round(min($sw, $sh) * 0.08)); // düz mod için taban hesap
        $margin   = max(10, (int)round($sw * 0.02));
        // GD alpha: 0=opak, 127=şeffaf → %opaklıktan ters hesap
        $alpha    = (int) round(127 - (127 * ($wmOpacityPct / 100)));

        // TTF font adayları
        $fontCandidates = [
            JPATH_ROOT . '/media/fonts/DejaVuSans.ttf',
            JPATH_ROOT . '/media/fonts/OpenSans.ttf',
            JPATH_ROOT . '/media/fonts/arial.ttf',
            JPATH_ROOT . '/media/fonts/verdana.ttf',
        ];
        $ttf = null;
        foreach ($fontCandidates as $fc) {
            if (is_file($fc)) { $ttf = $fc; break; }
        }

        // --- DIAGONAL WM (merkezde, nazik opaklık, gölgesiz) veya düz (panel + gölge) ---
        $ttfOk = ($ttf && function_exists('imagettfbbox'));

        if ($wmDiagonal && $ttfOk) {
            // Diagonal: kısa kenarın ~%10'u, hafif saydam, açık gri
            $fontSizeDiag = max(28, (int)round(min($sw, $sh) * 0.10));
            $angle = -35;

            // Rotasyonlu bbox
            $bbox = imagettfbbox($fontSizeDiag, $angle, $ttf, $text);

            // bbox köşeleri
            $xs = [$bbox[0], $bbox[2], $bbox[4], $bbox[6]];
            $ys = [$bbox[1], $bbox[3], $bbox[5], $bbox[7]];
            $minX = min($xs); $maxX = max($xs);
            $minY = min($ys); $maxY = max($ys);
            $textW = $maxX - $minX;
            $textH = $maxY - $minY;

            // BBox'ı merkeze: baseline için -minX/-minY düzeltmesi
            $x = (int)(($sw - $textW) / 2) - $minX;
            $y = (int)(($sh - $textH) / 2) - $minY;

            // Daha belirsiz görünüm: açık gri + ilave şeffaflık (alphaSoft)
            $alphaSoft = min(127, max(0, (int) round(127 - (127 * ($wmOpacityPct / 100)) + 15)));
            $col = imagecolorallocatealpha($image, 230, 230, 230, $alphaSoft);

            // Tek atış, gölgesiz
            imagettftext($image, $fontSizeDiag, $angle, $x, $y, $col, $ttf, $text);
        }
        elseif ($ttfOk) {
            // DÜZ (mevcut pozisyon mantığı) – panel + gölge
            $bbox  = imagettfbbox($fontSize, 0, $ttf, $text);
            $textW = abs($bbox[2] - $bbox[0]);
            $textH = abs($bbox[7] - $bbox[1]);

            $x = $margin; $y = $margin + $textH;
            if ($wmPos === 'top-right')       { $x = $sw - $textW - $margin; $y = $margin + $textH; }
            elseif ($wmPos === 'bottom-left') { $x = $margin;               $y = $sh - $margin; }
            elseif ($wmPos === 'bottom-right'){ $x = $sw - $textW - $margin; $y = $sh - $margin; }
            elseif ($wmPos === 'center')      { $x = (int)(($sw - $textW) / 2); $y = (int)(($sh + $textH) / 2); }

            $panelPad = (int) max(10, round($fontSize * 0.45));
            $panelX1 = $x - $panelPad;
            $panelY1 = $y - $textH - $panelPad;
            $panelX2 = $x + $textW + $panelPad;
            $panelY2 = $y + $panelPad;
            $panelCol = imagecolorallocatealpha($image, 0, 0, 0, min(127, $alpha + 20));
            imagefilledrectangle($image, $panelX1, $panelY1, $panelX2, $panelY2, $panelCol);

            $shadowCol = imagecolorallocatealpha($image, 0, 0, 0, min(127, $alpha + 30));
            imagettftext($image, $fontSize, 0, $x+2, $y+2, $shadowCol, $ttf, $text);

            $textCol   = imagecolorallocatealpha($image, 255, 255, 255, $alpha);
            imagettftext($image, $fontSize, 0, $x, $y, $textCol, $ttf, $text);
        }
        else {
            // TTF yoksa görünür fallback (kutu + yazı)
            $boxW = (int)max(160, round(min($sw,$sh)*0.35));
            $boxH = (int)max(60,  round(min($sw,$sh)*0.14));
            $x1 = (int)(($sw - $boxW)/2);
            $y1 = (int)(($sh - $boxH)/2);
            $x2 = $x1 + $boxW;
            $y2 = $y1 + $boxH;

            $bg = imagecolorallocatealpha($image, 200, 0, 0, 30);
            imagefilledrectangle($image, $x1, $y1, $x2, $y2, $bg);

            $font  = 5;
            $txt   = $text !== '' ? $text : 'Numistr';
            $txtW  = imagefontwidth($font) * strlen($txt);
            $txtH  = imagefontheight($font);
            $tx = (int)($x1 + ($boxW - $txtW)/2);
            $ty = (int)($y1 + ($boxH - $txtH)/2);
            $fg = imagecolorallocatealpha($image, 255, 255, 255, 50);
            imagestring($image, $font, $tx, $ty, $txt, $fg);
        }
        // --- /DIAGONAL ---

        // Çıkış (JPEG) + no-cache (raw header)
        header_remove('Cache-Control');
        header_remove('Expires');
        header('X-Numistr-WM: on');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        header('Content-Type: image/jpeg');
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        header('X-Content-Type-Options: nosniff');

        imagejpeg($image, null, 90);
        imagedestroy($image);
        exit;
    }
}
