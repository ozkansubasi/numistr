<?php
/**
 * NumisTR - Görsel Servisi
 *
 *  wm=0 : thumb (küçük, filigransız)
 *  wm=1 : popup (büyük, watermark'lı; merkezde diagonal ve büyük)
 *
 * - Lokal yoksa whitelist edilen uzak kaynaktan indirir (cURL).
 * - CA bundle otomatik: php.ini → /_secure/certs/cacert.pem
 * - British Museum "full_IMG" 404'larında "small_IMG" fallback.
 * - SSL chain hatasında (CA problemi) tek sefer insecure retry (WHITELIST alanlar).
 * - Debug: &debug=1  |  Test amaçlı TLS kapatma: &allow_insecure=1
 */

defined('_JEXEC') or die;

// --- SAFE GUARD: emit fonksiyonu yoksa tanımla (wm=0/1 çıktısı için) ---
if (!function_exists('numistr_emit_image')) {
    function numistr_emit_image(string $filePath): void {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($filePath);
        exit;
    }
}



use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
// Admin panelden watermark yazısı (güvenli okuma)
$wmText = 'NumisTR';
try {
    $compParams = \Joomla\CMS\Component\ComponentHelper::getParams('com_numistr');
    if (is_object($compParams) && method_exists($compParams, 'get')) {
        $val = $compParams->get('watermark_text', '');
        if ($val !== null && $val !== '') { $wmText = trim((string)$val); }
    }
} catch (\Throwable $e) { /* ignore */ }
$GLOBALS['__NUMISTR_WM_TEXT'] = $wmText;

/* ================== Helpers (guard'lı, çakışmasız) ================== */

if (!function_exists('numistr_ensure_dir')) {
function numistr_ensure_dir(string $dir): void { if (!is_dir($dir)) @mkdir($dir, 0775, true); }
}
if (!function_exists('numistr_basename')) {
function numistr_basename(string $path): string {
    $b = basename(str_replace('\\','/',$path));
    // UTF-8 karakterlere izin ver (ø, ü, ö, ş, ç, ğ, ı vb.)
    // Sadece tehlikeli karakterleri (path traversal, null byte) filtrele
    // \p{L} = Unicode harfler, \p{N} = Unicode rakamlar
    return preg_replace('~[^\p{L}\p{N}._()\+\- ]+~u','_',$b);
}
}
if (!function_exists('numistr_is_http')) {
function numistr_is_http(string $url): bool {
    $s = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return $s === 'http' || $s === 'https';
}
}
if (!function_exists('numistr_host')) {
function numistr_host(string $url): string { return strtolower((string)parse_url($url, PHP_URL_HOST)); }
}
if (!function_exists('numistr_is_whitelisted_host')) {
function numistr_is_whitelisted_host(string $url, array $whitelist): bool {
    $h = numistr_host($url);
    return $h !== '' && in_array($h, $whitelist, true);
}
}
if (!function_exists('numistr_pick_cainfo_path')) {
function numistr_pick_cainfo_path(): ?string {
    $ini = ini_get('curl.cainfo');
    if ($ini && @is_file($ini)) return $ini;
    $fallback = JPATH_ROOT . '/_secure/certs/cacert.pem';
    if (@is_file($fallback)) return $fallback;
    return null;
}
}
if (!function_exists('numistr_is_ssl_chain_error')) {
function numistr_is_ssl_chain_error(string $msg): bool {
    $m = strtolower($msg);
    return str_contains($m, 'ssl certificate problem') || str_contains($m, 'unable to get local issuer certificate');
}
}
if (!function_exists('numistr_bm_small_variant')) {
function numistr_bm_small_variant(string $url): ?string {
    if (numistr_host($url) !== 'media.britishmuseum.org') return null;
    $p = parse_url($url, PHP_URL_PATH) ?: '';
    if (strpos($p, '/full_IMG_') !== false) return str_replace('/full_IMG_', '/small_IMG_', $url);
    return null;
}
}
if (!function_exists('numistr_download_remote_curl')) {
function numistr_download_remote_curl(
    string $url, string $tmp, string $final, int $maxMB,
    ?string $cainfo, bool $allowInsecure, int $dbg, ?string $referer = null
): string {
    if (!function_exists('curl_init')) throw new RuntimeException('curl_missing');
    $fh = @fopen($tmp, 'wb'); if (!$fh) throw new RuntimeException('tmp_open_fail');

    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
    if ($referer === null) {
        $sch = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $hst = parse_url($url, PHP_URL_HOST) ?: '';
        if ($hst) $referer = $sch . '://' . $hst . '/';
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_FILE            => $fh,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_MAXREDIRS       => 5,
        CURLOPT_TIMEOUT         => 25,
        CURLOPT_CONNECTTIMEOUT  => 8,
        CURLOPT_USERAGENT       => $ua,
        CURLOPT_HTTPHEADER      => ['Accept: image/*','Accept-Language: en-US,en;q=0.9,tr;q=0.8'],
        CURLOPT_ENCODING        => '',
        CURLOPT_IPRESOLVE       => CURL_IPRESOLVE_V4,
        CURLOPT_REFERER         => $referer,
        CURLOPT_FAILONERROR     => true,
    ];
    if ($allowInsecure) {
        $opts[CURLOPT_SSL_VERIFYPEER] = false; $opts[CURLOPT_SSL_VERIFYHOST] = 0;
    } else {
        $opts[CURLOPT_SSL_VERIFYPEER] = true;  $opts[CURLOPT_SSL_VERIFYHOST] = 2;
        if ($cainfo && @is_file($cainfo)) $opts[CURLOPT_CAINFO] = $cainfo;
    }
    curl_setopt_array($ch, $opts);

    $ok   = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ct   = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err  = (string)curl_error($ch);
    curl_close($ch);
    fflush($fh); fclose($fh);

    if (!$ok || $code >= 400) { @unlink($tmp); throw new RuntimeException('http='.$code.' err='.$err); }
    if (stripos($ct, 'image/') !== 0) { @unlink($tmp); throw new RuntimeException('not_image ct='.$ct); }
    $bytes = @filesize($tmp);
    if ($bytes === false || $bytes > $maxMB*1024*1024) { @unlink($tmp); throw new RuntimeException('too_large bytes='.(int)$bytes); }
    if (!@rename($tmp, $final)) { @unlink($tmp); throw new RuntimeException('rename_fail'); }
    return $final;
}
}
if (!function_exists('numistr_make_thumb')) {
function numistr_make_thumb(string $src, string $out, int $maxEdge = 480): string {
    numistr_ensure_dir(dirname($out));
    if (extension_loaded('imagick')) {
        $im = new \Imagick($src);
        $im->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
        $im->thumbnailImage($maxEdge, $maxEdge, true, true);
        $im->setImageFormat('jpeg');
        $im->setImageCompression(\Imagick::COMPRESSION_JPEG);
        $im->setImageCompressionQuality(82);
        $im->stripImage();
        if (!$im->writeImage($out)) { $im->destroy(); throw new RuntimeException('thumb_write'); }
        $im->destroy();
        return $out;
    }

    $info = @getimagesize($src); if(!$info) throw new RuntimeException('bad_image');
    [$w,$h,$type] = $info;
    $ratio = ($w >= $h) ? $maxEdge/$w : $maxEdge/$h;
    $nw = max(1,(int)round($w*$ratio)); $nh = max(1,(int)round($h*$ratio));
    switch ($type) {
        case IMAGETYPE_JPEG: $in=imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $in=imagecreatefrompng($src);  break;
        case IMAGETYPE_WEBP: $in=function_exists('imagecreatefromwebp')?imagecreatefromwebp($src):null; break;
        default: $in=null;
    }
    if(!$in) throw new RuntimeException('unsupported_image_type');
    $outIm=imagecreatetruecolor($nw,$nh);
    imagecopyresampled($outIm,$in,0,0,0,0,$nw,$nh,$w,$h);
    imagejpeg($outIm,$out,82);
    imagedestroy($in); imagedestroy($outIm);
    return $out;
}
}
if (!function_exists('numistr_stream_remote_by_image_id')) {
function numistr_stream_remote_by_image_id(int $imageId): bool
{
    try {
        $db = \Joomla\CMS\Factory::getDbo();

        // İlk önce coin_id ve remote_url'yi al
        $q  = $db->getQuery(true)
            ->select([$db->quoteName('coin_id'), $db->quoteName('remote_url')])
            ->from($db->quoteName('coins_images'))
            ->where($db->quoteName('image_id') . ' = ' . (int)$imageId)
            ->setLimit(1);
        $db->setQuery($q);
        $row = $db->loadObject();

        if (!$row) return false;

        // DİREKT coins_images.remote_url kullan (image_id bazlı)
        $remote = trim((string)($row->remote_url ?? ''));

        // NOT: sikke-gorsel-url custom field'i SADECE intro image içindir
        // Her görselin kendi remote_url'i coins_images tablosunda vardır
    } catch (\Throwable $e) {
        return false;
    }

    $remote = trim($remote ?? '');
    if ($remote === '' || !preg_match('~^https?://~i', $remote)) return false;

    // cURL tercih; yoksa stream fallback
    $data = null; $type = 'image/jpeg'; $len = 0; $ok = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($remote);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'NumisTR-ImageProxy/1.1',
        ]);
        $data = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ct   = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $len  = (int)curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);
        if ($ct !== '' && stripos($ct, 'image/') === 0) $type = $ct;
        $ok = ($code >= 200 && $code < 400 && $data !== false && $data !== '');
    } else {
        $ctx  = stream_context_create(['http'=>['method'=>'GET','timeout'=>15,'ignore_errors'=>true]]);
        $data = @file_get_contents($remote, false, $ctx);
        $ok   = ($data !== false && $data !== '');
        $len  = strlen((string)$data);
    }

    if (!$ok) return false;

    if (!headers_sent()) {
        header('Content-Type: ' . $type);
        if ($len > 0) header('Content-Length: ' . (string)$len);
        header('Cache-Control: public, max-age=86400');
        header('X-NumisTR-Remote: 1');
    }
    echo $data;
    \Joomla\CMS\Factory::getApplication()->close();
    return true;
}
}

if (!function_exists('numistr_make_watermarked')) {
function numistr_make_watermarked(string $src, string $out, int $maxEdge = 1600, ?string $wmPng = null): string {
    numistr_ensure_dir(dirname($out));

    // Tercihler
    $ANGLE_DEG        = -33;   // diyagonal açı
    $OPACITY          = 0.24;  // 0..1
    $TOPRIGHT_SCALE   = 0.70;  // ≈ %30 küçült
    $PAD_K_INNER      = 0.28;  // metin label iç tamponu = fontSize * k
    $PAD_K_PLACED     = 0.03;  // köşeden dış tampon = min(W,H) * k
    // Sağ-üstten içeri ofset (döndürülmüş kutunun)
    $INSET_X_FR       = 0.55;  // daha sola almak için artırdım (0.50 → 0.75)
    $INSET_Y_FR       = 0.28;  // biraz daha aşağı

    // Debug header (aktif tanım çalışıyor mu görmek için)
    $__DBG = isset($_GET['wmdebug']) ? (int)$_GET['wmdebug'] : 0;
    if ($__DBG) {
        header('X-Numis-WM-Func: active');
        header('X-Numis-WM-Params: INSET_X_FR='.$INSET_X_FR.'; INSET_Y_FR='.$INSET_Y_FR.'; SCALE='.$TOPRIGHT_SCALE);
    }
    $text = isset($GLOBALS['__NUMISTR_WM_TEXT']) ? (string)$GLOBALS['__NUMISTR_WM_TEXT'] : 'NumisTR';
    // TTF font arama (ilk bulunan kullanılır)
    $fontCandidates = [
        JPATH_ROOT . '/media/fonts/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/TTF/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
    ];
    $FONT_PATH = null;
    foreach ($fontCandidates as $fc) { if (@is_file($fc)) { $FONT_PATH = $fc; break; } }

    if (extension_loaded('imagick')) {
        $im = new \Imagick($src);
        $im->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
        $im->thumbnailImage($maxEdge, $maxEdge, true, true);

        $W = $im->getImageWidth();
        $H = $im->getImageHeight();
        $placePad = (int)round(min($W, $H) * $PAD_K_PLACED);
        if ($placePad < 6) $placePad = 6;

        if ($wmPng && is_file($wmPng)) {
            // --- PNG WATERMARK (sağ-üst + içe ofset) ---
            $wm = new \Imagick($wmPng);
            $wm->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
            $wm->setImageBackgroundColor(new \ImagickPixel('transparent'));

            // görsel genişliğinin ~%70'i hedef (küçültme)
            $targetW = (int)floor($W * $TOPRIGHT_SCALE);
            if ($targetW < 300) $targetW = 300;
            $wm->thumbnailImage($targetW, 0, true);

            if (method_exists($wm, 'setImageOpacity')) { $wm->setImageOpacity($OPACITY); }
            $wm->rotateImage(new \ImagickPixel('transparent'), $ANGLE_DEG);

            $wmW = $wm->getImageWidth();
            $wmH = $wm->getImageHeight();

            $x = max(0, $W - $placePad - $wmW - (int)round($wmW * $INSET_X_FR));
            $y = max(0, $placePad + (int)round($wmH * $INSET_Y_FR));

            if ($__DBG) header('X-Numis-WM-XY: x='.$x.', y='.$y.', W='.$W.', H='.$H.', mode=png');
            $im->compositeImage($wm, \Imagick::COMPOSITE_OVER, $x, $y);
            $wm->destroy();
        } else {
            // --- METİN WATERMARK (label + sağ-üst, içe ofset) ---
            $diag     = sqrt($W*$W + $H*$H);
            $baseSize = max(36, (int)round($diag * 0.13));
            $baseSize = (int)floor($baseSize * $TOPRIGHT_SCALE); // %30 küçült
            if ($baseSize < 24) $baseSize = 24;

            $draw = new \ImagickDraw();
            if ($FONT_PATH) { $draw->setFont($FONT_PATH); }
            $draw->setTextAntialias(true);
            $draw->setFillColor(new \ImagickPixel('white'));
            if (method_exists($draw, 'setFillOpacity'))   { $draw->setFillOpacity($OPACITY); }
            $draw->setStrokeColor(new \ImagickPixel('black'));
            if (method_exists($draw, 'setStrokeOpacity')) { $draw->setStrokeOpacity($OPACITY); }
            $draw->setStrokeWidth(3);
            $draw->setGravity(\Imagick::GRAVITY_CENTER);
            $draw->setFontSize($baseSize);

            $metrics = @$im->queryFontMetrics($draw, $text);
            $tw = isset($metrics['textWidth'])  ? (float)$metrics['textWidth']  : (float)($baseSize * 6.2);
            $th = isset($metrics['textHeight']) ? (float)$metrics['textHeight'] : (float)($baseSize * 1.25);
            $innerPad = (int)round($baseSize * $PAD_K_INNER);
            if ($innerPad < 4) $innerPad = 4;

            $labelW = (int)ceil($tw + 2*$innerPad);
            $labelH = (int)ceil($th + 2*$innerPad);
            if ($labelW < 10) $labelW = 10;
            if ($labelH < 10) $labelH = 10;

            $label = new \Imagick();
            $label->newImage($labelW, $labelH, new \ImagickPixel('transparent'));
            $label->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);

            // Metni label merkezine yaz, sonra döndür
            $label->annotateImage($draw, 0, 0, 0, $text);
            $label->rotateImage(new \ImagickPixel('transparent'), $ANGLE_DEG);

            $wmW = $label->getImageWidth();
            $wmH = $label->getImageHeight();
            
            // EK AYARLAMA DEĞERLERİ
            $offsetX = 60; // Sağ tarafa kaç piksel kaydırmak istediğiniz (örneğin 30 piksel)
            $offsetY = 30; // Aşağıya kaç piksel kaydırmak istediğiniz (örneğin 30 piksel)
            
            $x = $placePad + $offsetX; // Sol kenardan boşluk + ek sağa kaydırma
            $y = $placePad + $offsetY; // Üst kenardan boşluk + ek aşağıya kaydırma

            if ($__DBG) header('X-Numis-WM-XY: x='.$x.', y='.$y.', W='.$W.', H='.$H.', mode=text');
            $im->compositeImage($label, \Imagick::COMPOSITE_OVER, $x, $y);
            $label->destroy();
        }

        // JPEG çıkış
        $im->setImageFormat('jpeg');
        $im->setImageCompression(\Imagick::COMPRESSION_JPEG);
        $im->setImageCompressionQuality(83);
        $im->stripImage();
        if (!$im->writeImage($out)) { $im->destroy(); throw new \RuntimeException('wm_write'); }
        $im->destroy();
        return $out;
    }

    // --- GD fallback (yaklaşık sağ-üst) ---
    $info = @getimagesize($src); if(!$info) throw new \RuntimeException('bad_image');
    [$w,$h,$type] = $info;
    $ratio = ($w >= $h) ? $maxEdge/$w : $maxEdge/$h;
    $nw = max(1,(int)round($w*$ratio)); $nh = max(1,(int)round($h*$ratio));
    switch ($type) {
        case IMAGETYPE_JPEG: $in=imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $in=imagecreatefrompng($src);  break;
        case IMAGETYPE_WEBP: $in=function_exists('imagecreatefromwebp')?imagecreatefromwebp($src):null; break;
        default: $in=null;
    }
    if(!$in) throw new \RuntimeException('unsupported_image_type');
    $outIm=imagecreatetruecolor($nw,$nh);
    imagecopyresampled($outIm,$in,0,0,0,0,$nw,$nh,$w,$h);

   
    $placePad = (int)round(min($nw,$nh) * $PAD_K_PLACED);
    if ($placePad < 6) $placePad = 6;

    if (function_exists('imagettftext') && ($FONT_PATH || @is_file($fontCandidates[0]) )) {
        $ttf = $FONT_PATH ?: $fontCandidates[0];
        $fs  = max(24, (int)round(sqrt($nw*$nw+$nh*$nh) * 0.13 * $TOPRIGHT_SCALE));
        $black = imagecolorallocatealpha($outIm, 0, 0, 0, (int)round(127*(1-$OPACITY)));
        $white = imagecolorallocatealpha($outIm,255,255,255,(int)round(127*(1-$OPACITY)));
        // Döndürülmüş metnin kaplayacağı alanı tam olarak hesapla
        $bbox = imagettfbbox($fs, -33, $ttf, $text);

        // EK AYARLAMA DEĞERLERİ
        $offsetX = 180; // Sağ tarafa kaç piksel kaydırmak istediğiniz (örneğin 30 piksel)
        $offsetY = 120; // Aşağıya kaç piksel kaydırmak istediğiniz (örneğin 30 piksel)
        
        $x = $placePad - $bbox[6] + $offsetX; // Sol kenarı hizala + ek sağa kaydırma
        $y = $placePad - $bbox[7] + $offsetY; // Üst kenarı hizala + ek aşağıya kaydırma

        if ($__DBG) header('X-Numis-WM-XY: x='.$x.', y='.$y.', W='.$nw.', H='.$nh.', mode=gd');
        imagettftext($outIm, $fs, -33, $x, $y, $black, $ttf, $text);
        imagettftext($outIm, $fs, -33, $x, $y, $white, $ttf, $text);
    } else {
        $alpha = (int)round(127 * (1 - $OPACITY));
        $col=imagecolorallocatealpha($outIm,255,255,255,$alpha);
        imagestring($outIm,5,$nw-$placePad-70,$placePad,$text,$col);
    }

    imagejpeg($outIm,$out,83);
    imagedestroy($in); imagedestroy($outIm);
    return $out;
}
}


if (!function_exists('numistr_emit_image')) {
function numistr_emit_image(string $filePath): void {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($filePath);
    exit;
}
}

/* ================== Main ================== */

$app     = Factory::getApplication();
$db      = Factory::getDbo();
$params  = ComponentHelper::getParams('com_numistr');

$id      = (int)$app->input->getInt('id', 0);
$wm      = (int)$app->input->getInt('wm', 0);        // 0: thumb, 1: popup
$dbg     = (int)$app->input->getInt('debug', 0);
$insecQ  = (int)$app->input->getInt('allow_insecure', 0); // sadece test
$force   = (int)$app->input->getInt('force', 0); // test için cache kırma
$vw   = (int)$app->input->getInt('vw', 0);
$vh   = (int)$app->input->getInt('vh', 0);
$dpr  = (float)$app->input->getFloat('dpr', 0);
// watermark fonksiyonu içinde erişelim diye global'e koyuyoruz
$GLOBALS['__NUMISTR_VW'] = $vw;
$GLOBALS['__NUMISTR_VH'] = $vh;
$GLOBALS['__NUMISTR_DPR']= $dpr;



if ($id <= 0) { http_response_code(400); exit('bad request'); }

// Bölge eşlemesi (Options → JSON)
$regionJson = null;
foreach (['region_map_json','region_map','bolge_map_json','region_mapping_json'] as $k) {
    $v = $params->get($k, null);
    if ($v !== null && $v !== '') { $regionJson = (string)$v; break; }
}
$REGION_MAP = [];
if ($regionJson) {
    $tmp = json_decode($regionJson, true);
    if (is_array($tmp)) {
        $REGION_MAP = $tmp;
    }
}

// DEBUG: Region map yüklendi mi?
if ($dbg && empty($REGION_MAP)) {
    header('X-NumisTR-RegionMap: EMPTY or NOT LOADED');
    header('X-NumisTR-RegionJson: ' . ($regionJson ? 'found' : 'null'));
}

// Whitelist (CSV/JSON)
$whitelistRaw = (string)($params->get('remote_whitelist', '') ?? '');
$WHITELIST = [];
if ($whitelistRaw !== '') {
    $mj = json_decode($whitelistRaw, true);
    $WHITELIST = is_array($mj)
        ? array_values(array_filter(array_map(fn($s)=>strtolower(trim((string)$s)), $mj)))
        : array_values(array_filter(array_map(fn($s)=>strtolower(trim($s)), explode(',', $whitelistRaw))));
}
if (!$WHITELIST) $WHITELIST = ['gallica.bnf.fr','media.britishmuseum.org'];

// Yollar
$PRIVATE_ORIG_BASE = rtrim(JPATH_ROOT,'/').'/../sikke';
$CACHE_BASE        = rtrim(JPATH_ROOT,'/').'/../sikke_cache';
$CACHE_ORIG_DIR    = $CACHE_BASE . '/original';
$CACHE_TH_DIR      = $CACHE_BASE . '/thumbs';
$CACHE_WM_DIR      = $CACHE_BASE . '/large_wm';
$WATERMARK_PNG     = JPATH_ROOT . '/media/watermark.png';
$THUMB_MAX_EDGE    = 480;
$LARGE_MAX_EDGE    = 1600;
$REMOTE_MAX_MB     = 15;

// Dizinleri hazırla
numistr_ensure_dir($CACHE_BASE);
numistr_ensure_dir($CACHE_ORIG_DIR);
numistr_ensure_dir($CACHE_TH_DIR);
numistr_ensure_dir($CACHE_WM_DIR);

// Kayıt
$q = $db->getQuery(true)
    ->select(['ci.coin_id','ci.filename','ci.remote_url'])
    ->from($db->quoteName('coins_images','ci'))
    ->where('ci.image_id='.(int)$id)->setLimit(1);
$db->setQuery($q);
$row = $db->loadObject();
if (!$row) { http_response_code(404); exit('not found'); }

// Kategori alias → klasör
$q = $db->getQuery(true)
    ->select(['cat.alias AS cat_alias'])
    ->from($db->quoteName('#__content','c'))
    ->join('INNER',$db->quoteName('#__categories','cat').' ON cat.id=c.catid')
    ->where('c.id='.(int)$row->coin_id)->setLimit(1);
$db->setQuery($q);
$cat = $db->loadObject();
$catAlias = $cat ? (string)$cat->cat_alias : '';
$folder   = $REGION_MAP[$catAlias] ?? $catAlias ?: 'unknown';


// Kaynak: önce local
$src = null;
$filenameOnly = '';
if (!empty($row->filename)) {
    $filenameOnly = numistr_basename((string)$row->filename);
    $local = rtrim($PRIVATE_ORIG_BASE,'/').'/'.$folder.'/'.$filenameOnly;

    // DEBUG: Dosya yolu detayları
    if ($dbg) {
        header('X-Debug-ID: ' . $id);
        header('X-Debug-CatAlias: ' . $catAlias);
        header('X-Debug-Folder: ' . $folder);
        header('X-Debug-Filename-Raw: ' . urlencode($row->filename));
        header('X-Debug-Filename-Clean: ' . urlencode($filenameOnly));
        header('X-Debug-Local-Path: ' . urlencode($local));
        header('X-Debug-PRIVATE_ORIG_BASE: ' . urlencode($PRIVATE_ORIG_BASE));
        header('X-Debug-File-Exists: ' . (file_exists($local) ? 'YES' : 'NO'));
        header('X-Debug-Is-File: ' . (is_file($local) ? 'YES' : 'NO'));
        header('X-Debug-Is-Readable: ' . (is_readable($local) ? 'YES' : 'NO'));
        // Realpath kontrol
        $realLocal = @realpath($local);
        header('X-Debug-Realpath: ' . ($realLocal ? urlencode($realLocal) : 'FALSE'));
    }

    if (@is_file($local)) $src = $local;
}

// Local yoksa remote stream denemesi - DÜZELTİLDİ: $imageId yerine $id
if ($src === null) {
    if ($dbg) {
        header('X-Debug-Local-Failed: YES');
        header('X-Debug-Remote-URL: ' . urlencode($row->remote_url ?? ''));
    }
    if (numistr_stream_remote_by_image_id($id)) {
        exit; // Stream başarılı, çık
    }
}

// Local yoksa remote (whitelist + cURL)
if (!$src && !empty($row->remote_url) && numistr_is_http($row->remote_url) && numistr_is_whitelisted_host($row->remote_url,$WHITELIST)) {
    $cainfo = numistr_pick_cainfo_path();
    $key    = sha1($row->remote_url);
    $tmp    = $CACHE_ORIG_DIR.'/'.$key.'.tmp';
    $final  = $CACHE_ORIG_DIR.'/'.$key.'.bin';

    if (!@is_file($final)) {
        try {
            $ref = (numistr_host($row->remote_url) === 'media.britishmuseum.org') ? 'https://www.britishmuseum.org/' : null;
            // 1) güvenli dene
            numistr_download_remote_curl($row->remote_url,$tmp,$final,$REMOTE_MAX_MB,$cainfo,false,$dbg,$ref);
        } catch (\Throwable $e) {
            $msg  = $e->getMessage();
            $code = (preg_match('~http=(\d+)~',$msg,$m)?(int)$m[1]:0);

            // 2) SSL chain hatası → tek sefer insecure retry
            if (numistr_is_ssl_chain_error($msg) || $insecQ) {
                try {
                    $ref = (numistr_host($row->remote_url) === 'media.britishmuseum.org') ? 'https://www.britishmuseum.org/' : null;
                    numistr_download_remote_curl($row->remote_url,$tmp,$final,$REMOTE_MAX_MB,$cainfo,true,$dbg,$ref);
                } catch (\Throwable $e2) {
                    // 3) BM ise small varyanta düş (insecure)
                    $bmSmall = numistr_bm_small_variant($row->remote_url);
                    if ($bmSmall) {
                        $key2   = sha1($bmSmall);
                        $tmp2   = $CACHE_ORIG_DIR.'/'.$key2.'.tmp';
                        $final2 = $CACHE_ORIG_DIR.'/'.$key2.'.bin';
                        numistr_download_remote_curl($bmSmall,$tmp2,$final2,$REMOTE_MAX_MB,$cainfo,true,$dbg,'https://www.britishmuseum.org/');
                        $final  = $final2;
                    } else {
                        if ($dbg) { header('Content-Type: text/plain; charset=UTF-8'); echo "remote fail debug: ".$e2->getMessage()."\n"; echo "cainfo: ".($cainfo?:'(none)')."\n"; exit; }
                        http_response_code(404); exit('remote fail');
                    }
                }
            }
            // 4) Güvenli 4xx ve BM ise → small varyantı (güvenli)
            elseif ($code >= 400) {
                $bmSmall = numistr_bm_small_variant($row->remote_url);
                if ($bmSmall) {
                    $key2   = sha1($bmSmall);
                    $tmp2   = $CACHE_ORIG_DIR.'/'.$key2.'.tmp';
                    $final2 = $CACHE_ORIG_DIR.'/'.$key2.'.bin';
                    numistr_download_remote_curl($bmSmall,$tmp2,$final2,$REMOTE_MAX_MB,$cainfo,false,$dbg,'https://www.britishmuseum.org/');
                    $final = $final2;
                } else {
                    if ($dbg) { header('Content-Type: text/plain; charset=UTF-8'); echo "remote fail debug: ".$msg."\n"; echo "cainfo: ".($cainfo?:'(none)')."\n"; exit; }
                    http_response_code(404); exit('remote fail');
                }
            }
            // 5) diğer hatalar
            else {
                if ($dbg) { header('Content-Type: text/plain; charset=UTF-8'); echo "remote fail debug: ".$msg."\n"; echo "cainfo: ".($cainfo?:'(none)')."\n"; exit; }
                http_response_code(404); exit('remote fail');
            }
        }
    }
    $src = $final;
}

if (!$src) { http_response_code(404); exit('not found'); }

// === ÇIKIŞ YOLLARI (safety) ===
if (!isset($baseName) || $baseName === '') {
    $baseName = ($filenameOnly ?? '') !== '' ? pathinfo($filenameOnly, PATHINFO_FILENAME)
                                             : sha1((string)($row->remote_url ?? '') . '_' . (string)$id);
}
$thumbDir  = rtrim($CACHE_TH_DIR, '/').'/'.$folder;  numistr_ensure_dir($thumbDir);
$wmDir     = rtrim($CACHE_WM_DIR,  '/').'/'.$folder; numistr_ensure_dir($wmDir);
$WM_VER = 2;
$thumbPath = $thumbDir . '/' . $baseName . '.jpg';
$wmPath    = $wmDir    . '/' . $baseName . '_v' . $WM_VER . '.jpg';
if ($force && @is_file($wmPath)) { @unlink($wmPath); }

if ($wm === 0) {
    if (!@is_file($thumbPath)) {
        try { numistr_make_thumb($src,$thumbPath,$THUMB_MAX_EDGE); }
        catch (\Throwable $e) {
            if ($dbg) { header('Content-Type: text/plain; charset=UTF-8'); echo 'thumb gen failed: '.$e->getMessage(); exit; }
            http_response_code(500); exit('thumb gen failed');
        }
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($thumbPath);
    exit;
} else {
    if (!@is_file($wmPath)) {
        try { numistr_make_watermarked($src,$wmPath,$LARGE_MAX_EDGE,null); }
        catch (\Throwable $e) {
            if ($dbg) { header('Content-Type: text/plain; charset=UTF-8'); echo 'wm gen failed: '.$e->getMessage(); exit; }
            http_response_code(500); exit('wm gen failed');
        }
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($wmPath);
    exit;
}