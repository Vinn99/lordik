<?php
/**
 * app/Controllers/BaseController.php
 *
 * Tim: Backend
 *
 * Controller bertanggung jawab untuk:
 *   - Menerima request (GET/POST)
 *   - Memanggil Model untuk data
 *   - Merender View
 *   - Redirect
 *
 * Controller TIDAK boleh:
 *   - Menulis SQL langsung (gunakan Model)
 *   - Menulis HTML langsung (gunakan View)
 */

abstract class BaseController
{
    /** @var array Data yang diteruskan ke view */
    protected array $data = [];

    /** Path ke direktori Views */
    protected string $viewPath;

    public function __construct()
    {
        $this->viewPath = BASE_PATH . '/app/Views/';
    }

    /**
     * Render view file dengan data
     * Contoh: $this->view('admin/dashboard', ['title' => 'Dashboard'])
     */
    protected function view(string $view, array $data = []): void
    {
        extract(array_merge($this->data, $data));
        $viewFile = $this->viewPath . ltrim($view, '/') . '.php';

        if (!file_exists($viewFile)) {
            throw new RuntimeException("View tidak ditemukan: {$viewFile}");
        }

        require $viewFile;
    }

    /**
     * Render layout header
     */
    protected function layoutHeader(string $pageTitle = ''): void
    {
        $pageTitle = $pageTitle ?: (APP_NAME);
        require $this->viewPath . 'layouts/header.php';
    }

    /**
     * Render layout footer
     */
    protected function layoutFooter(): void
    {
        require $this->viewPath . 'layouts/footer.php';
    }

    /**
     * Output JSON (untuk API/AJAX)
     */
    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Ambil input POST yang sudah di-sanitize
     */
    protected function input(string $key, mixed $default = ''): mixed
    {
        $val = $_POST[$key] ?? $default;
        return is_string($val) ? sanitize($val) : $val;
    }

    /**
     * Ambil input GET yang sudah di-sanitize
     */
    protected function query(string $key, mixed $default = ''): mixed
    {
        $val = $_GET[$key] ?? $default;
        return is_string($val) ? sanitize($val) : $val;
    }

    /**
     * Validasi CSRF & cek method POST
     */
    protected function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            die('Method Not Allowed');
        }
        validateCsrf();
    }

    /**
     * Cek apakah request adalah AJAX
     */
    protected function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
}
