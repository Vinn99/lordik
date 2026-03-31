<?php
/**
 * helpers/functions.php — Tim: Backend
 * Fungsi utility global.
 */

function redirect(string $path): never {
    header('Location: ' . (str_starts_with($path,'http') ? $path : APP_URL.$path));
    exit;
}
function setFlash(string $type, string $message): void { $_SESSION['flash'][] = ['type'=>$type,'message'=>$message]; }
function getFlash(): array { $f=$_SESSION['flash']??[]; unset($_SESSION['flash']); return $f; }
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES,'UTF-8'); }
function sanitize(string $s): string { return trim(strip_tags($s)); }
function truncate(string $t, int $n=100): string { return mb_strlen($t)>$n ? mb_substr($t,0,$n).'…' : $t; }
function formatDate(?string $d, string $fmt='d M Y'): string { return $d ? date($fmt,strtotime($d)) : '-'; }
function formatCurrency(?float $n): string { return $n===null ? '-' : 'Rp '.number_format($n,0,',','.'); }
function getClientIp(): string { return $_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['HTTP_CLIENT_IP']??$_SERVER['REMOTE_ADDR']??''; }
function getUserAgent(): string { return substr($_SERVER['HTTP_USER_AGENT']??'',0,500); }
function generateCsrfToken(): string { if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function verifyCsrfToken(string $t): bool { return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'],$t); }
function csrfField(): string { return '<input type="hidden" name="csrf_token" value="'.e(generateCsrfToken()).'">'; }
function validateCsrf(): void { if ($_SERVER['REQUEST_METHOD']==='POST' && !verifyCsrfToken($_POST['csrf_token']??'')) { http_response_code(403); die('Invalid CSRF token.'); } }
function paginate(int $total, int $page, int $perPage=PER_PAGE): array {
    $totalPages=max(1,(int)ceil($total/$perPage)); $page=max(1,min($page,$totalPages));
    return ['total'=>$total,'per_page'=>$perPage,'current'=>$page,'total_pages'=>$totalPages,'offset'=>($page-1)*$perPage];
}

function statusBadge(string $status): string {
    $cfg = [
        'pending'        => ['warning',  'text-dark', 'clock',            'Pending'],
        'frozen'         => ['secondary','text-white','pause-circle',      'Ditangguhkan'],
        'reviewed'       => ['info',     'text-white','eye',               'Ditinjau'],
        'shortlisted'    => ['primary',  'text-white','star-fill',         'Shortlist'],
        'accepted'       => ['success',  'text-white','check-circle-fill', 'Diterima'],
        'rejected'       => ['danger',   'text-white','x-circle-fill',     'Ditolak'],
        'submitted'      => ['secondary','text-white','send',              'Dikirim'],
        'approved'       => ['success',  'text-white','check-circle-fill', 'Disetujui'],
        'closed'         => ['dark',     'text-white','lock-fill',         'Ditutup'],
        'employed'       => ['success',  'text-white','briefcase-fill',    'Bekerja'],
        'unemployed'     => ['secondary','text-white','person-dash',       'Belum Bekerja'],
        'entrepreneur'   => ['info',     'text-white','shop',              'Wirausaha'],
        'continuing_edu' => ['primary',  'text-white','book-fill',         'Lanjut Studi'],
    ];
    [$bg,$tc,$icon,$label] = $cfg[$status] ?? ['secondary','text-white','question-circle',$status];
    return "<span class=\"badge bg-{$bg} {$tc} d-inline-flex align-items-center gap-1\"><i class=\"bi bi-{$icon}\" style=\"font-size:.7em\"></i>{$label}</span>";
}

function workStatusLabel(string $s): string {
    return match($s) {
        'employed'=>'Bekerja','unemployed'=>'Belum Bekerja',
        'entrepreneur'=>'Wirausaha','continuing_edu'=>'Lanjut Studi',default=>ucfirst($s)
    };
}

function backButton(string $fallbackUrl='', string $label='Kembali'): string {
    $fb = $fallbackUrl ? "'".addslashes(APP_URL.$fallbackUrl)."'" : "document.referrer||'".APP_URL."'";
    return "<button onclick=\"window.history.length>1?history.back():location.href={$fb}\" class=\"btn-back\"><i class=\"bi bi-arrow-left\"></i> ".e($label)."</button>";
}

function paginationLinks(array $pager, array $queryParams=[]): string {
    if ($pager['total_pages']<=1) return '';
    $links='<nav aria-label="Navigasi halaman"><ul class="pagination pagination-sm mb-0 flex-wrap">';
    for ($p=1;$p<=$pager['total_pages'];$p++) {
        $active = $p===$pager['current'];
        $url    = '?'.http_build_query(array_merge($queryParams,['page'=>$p]));
        $links .= "<li class=\"page-item ".($active?'active':'')."\"><a class=\"page-link\" href=\"{$url}\">{$p}</a></li>";
    }
    return $links.'</ul></nav>';
}

function ageFromDate(?string $dob): ?int {
    if (!$dob) return null;
    return (int)date_diff(new DateTime($dob),new DateTime())->y;
}
