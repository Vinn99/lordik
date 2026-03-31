<?php
// public/auth/logout.php
validateCsrf();
logoutUser();
setFlash('success', 'Anda berhasil logout.');
redirect('/auth/login.php');
