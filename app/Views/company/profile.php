<?php
// public/company/profile.php
require_once BASE_PATH . '/helpers/upload_helper.php';
require_once BASE_PATH . '/helpers/notification_helper.php';

requireCompany();

$errors  = [];
$profile = CompanyModel::getProfile(currentUserId());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['_action'] ?? 'save';

    if ($action === 'save') {
        $result = CompanyModel::createOrUpdateProfile(currentUserId(), $_POST);
        if ($result['success']) {
            setFlash('success', 'Profil perusahaan berhasil disimpan.');
            redirect('/company/profile.php');
        } else {
            $errors = $result['errors'] ?? [];
        }
    }

    if ($action === 'upload_logo' && isset($_FILES['logo_file'])) {
        $result = CompanyModel::uploadLogo(currentUserId(), $_FILES['logo_file']);
        if ($result['success']) {
            setFlash('success', 'Logo berhasil diupload.');
        } else {
            setFlash('danger', $result['message']);
        }
        redirect('/company/profile.php');
    }

    if ($action === 'set_pin') {
        $pin    = $_POST['pin'] ?? '';
        $result = CompanyModel::setResetPin(currentUserId(), $pin);
        if ($result['success']) {
            setFlash('success', 'Reset PIN berhasil disimpan.');
        } else {
            setFlash('danger', $result['message']);
        }
        redirect('/company/profile.php');
    }
}

$profile   = CompanyModel::getProfile(currentUserId());
$pageTitle = 'Profil Perusahaan — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <?= backButton() ?>
<h4 class="fw-bold mb-4"><i class="bi bi-building me-2 text-primary"></i>Profil Perusahaan</h4>

        <!-- Profile Form -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom fw-semibold">Informasi Perusahaan</div>
            <div class="card-body p-4">
                <?php if ($errors): ?>
                <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="_action" value="save">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Nama Perusahaan <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" required
                                   value="<?= e($profile['company_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Industri</label>
                            <input type="text" name="industry" class="form-control"
                                   value="<?= e($profile['industry'] ?? '') ?>"
                                   placeholder="Teknologi, Manufaktur, dll">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kota</label>
                            <input type="text" name="city" class="form-control"
                                   value="<?= e($profile['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">No. Telepon</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?= e($profile['phone'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Alamat Lengkap</label>
                            <textarea name="address" class="form-control" rows="2"><?= e($profile['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Website</label>
                            <input type="url" name="website" class="form-control"
                                   placeholder="https://"
                                   value="<?= e($profile['website'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Deskripsi Perusahaan</label>
                            <textarea name="description" class="form-control" rows="4"
                                      placeholder="Ceritakan tentang perusahaan Anda..."><?= e($profile['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logo Upload -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom fw-semibold">Logo Perusahaan</div>
            <div class="card-body">
                <?php if ($profile && $profile['logo_path']): ?>
                <img src="<?= APP_URL ?>/files/serve.php?type=logo&path=<?= urlencode($profile['logo_path']) ?>"
                     alt="Logo" class="mb-3 rounded" style="max-height:100px;">
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="_action" value="upload_logo">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <input type="file" name="logo_file" class="form-control" accept=".jpg,.jpeg,.png" required>
                            <div class="form-text">JPG/PNG, maks 1MB</div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-upload me-1"></i>Upload Logo</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reset PIN -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom fw-semibold">
                <i class="bi bi-shield-lock me-2 text-warning"></i>Reset PIN (untuk reset password)
            </div>
            <div class="card-body">
                <p class="small text-muted">PIN ini digunakan untuk mereset password akun Anda tanpa email. Simpan PIN ini dengan aman.</p>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="_action" value="set_pin">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <input type="password" name="pin" class="form-control"
                                   placeholder="Min. 6 karakter" required minlength="6">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-warning w-100">Set PIN</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
