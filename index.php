<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buana Gardenia</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <div class="login-card">
        <h2>🌿 Buana Gardenia</h2>
        <p>Silakan login untuk melanjutkan</p>

        <form method="POST" action="login.php">
            <div class="input-group">
                <span>📧</span>
                <input type="email" name="email" placeholder="Email" required>
            </div>

            <div class="input-group">
                <span>🔒</span>
                <input type="password" name="password" id="password" placeholder="Password" required>
                <button type="button" onclick="togglePassword()">👁</button>
            </div>

            <button type="submit" name="login" class="btn-login">Login</button>
        </form>

        <p class="footer">🌱 Belum punya akun? <a href="#">Daftar</a></p>
    </div>
</div>

<script>
function togglePassword() {
    var pass = document.getElementById("password");
    pass.type = pass.type === "password" ? "text" : "password";
}
</script>

</body>
</html>