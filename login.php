<?php
session_start();
include("conexion.php");

// Si ya inició sesión, lo manda directo al panel
if (isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario  = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Consulta con sentencia preparada (evita inyección SQL)
    $stmt = mysqli_prepare($conexion, "SELECT * FROM usuarios WHERE usuario = ?");
    mysqli_stmt_bind_param($stmt, "s", $usuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);

    if ($resultado && mysqli_num_rows($resultado) == 1) {
        $row = mysqli_fetch_assoc($resultado);

        // Verificación de contraseña (texto plano, igual que en el código original)
        if ($password == $row['password']) {
            $_SESSION['usuario']       = $row['usuario'];
            $_SESSION['nombre_admin']  = $row['nombre'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "El usuario no existe.";
    }
}
?>
<!DOCTYPE html>
<html lang="es" class="h-100">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AuraSync | Sistema de Control de Acceso</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --navy-950:#0c1024;
    --navy-900:#121735;
    --navy-800:#1a2148;
    --accent-500:#5b6ff2;
    --accent-400:#7c8bf5;
    --teal-400:#2dd4bf;
    --text-muted:#8a90a8;
    --border-soft:#e7e9f2;
    --ink:#1c2033;
  }
  *{ box-sizing:border-box; }
  html,body{ height:100%; margin:0; }
  body{
    font-family:'Manrope', sans-serif;
    background:#e9ebf3;
    display:flex;
    align-items:center;
    justify-content:center;
    min-height:100vh;
    padding:24px;
  }

  .auth-card{
    width:100%;
    max-width:920px;
    min-height:560px;
    display:grid;
    grid-template-columns:1fr 1fr;
    background:#fff;
    border-radius:20px;
    overflow:hidden;
    box-shadow:0 30px 60px -20px rgba(12,16,36,0.35);
  }

  /* ---------- Panel izquierdo ---------- */
  .brand-panel{
    background:linear-gradient(160deg, var(--navy-900) 0%, var(--navy-950) 100%);
    color:#fff;
    padding:44px 40px;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    position:relative;
    overflow:hidden;
  }
  .brand-mark{
    display:flex;
    align-items:center;
    gap:10px;
    font-family:'Sora', sans-serif;
    font-weight:700;
    font-size:19px;
    letter-spacing:0.3px;
    z-index:2;
  }
  .brand-mark svg{ flex-shrink:0; }
  .brand-mark .light{ font-weight:400; opacity:0.85; }

  .brand-copy{ z-index:2; margin-top:20px; }
  .brand-copy h1{
    font-family:'Sora', sans-serif;
    font-size:26px;
    font-weight:800;
    letter-spacing:0.5px;
    margin:0 0 4px;
  }
  .brand-copy h2{
    font-family:'Sora', sans-serif;
    font-size:17px;
    font-weight:600;
    margin:0 0 10px;
    color:#dfe3f7;
  }
  .brand-copy p{
    font-size:13.5px;
    line-height:1.55;
    color:var(--text-muted);
    max-width:270px;
    margin:0;
  }

  .brand-visual{
    z-index:2;
    margin-top:28px;
    height:150px;
    border-radius:14px;
    background:rgba(255,255,255,0.03);
    border:1px solid rgba(255,255,255,0.06);
    position:relative;
    overflow:hidden;
    display:flex;
    align-items:flex-end;
    justify-content:center;
  }
  .brand-visual .glow{
    position:absolute;
    width:150px; height:150px;
    left:50%; top:58%;
    transform:translate(-50%,-50%);
    background:radial-gradient(circle, rgba(93,111,242,0.55) 0%, rgba(45,212,191,0.25) 45%, transparent 72%);
    filter:blur(6px);
  }
  .brand-visual .ring{
    position:absolute;
    width:64px; height:64px;
    left:50%; top:70%;
    transform:translate(-50%,-50%);
    border-radius:50%;
    border:2px solid rgba(124,139,245,0.55);
    box-shadow:0 0 24px rgba(93,111,242,0.55);
  }
  .brand-visual svg{ position:relative; z-index:2; padding-bottom:10px; }

  /* ---------- Panel derecho ---------- */
  .form-panel{
    padding:48px 44px;
    display:flex;
    flex-direction:column;
    justify-content:center;
  }
  .form-panel label{
    display:block;
    font-size:12.5px;
    font-weight:700;
    color:var(--ink);
    margin-bottom:6px;
  }
  .form-panel .field{ margin-bottom:16px; }
  .form-panel input[type="text"],
  .form-panel input[type="password"]{
    width:100%;
    padding:11px 14px;
    border:1.5px solid var(--border-soft);
    border-radius:9px;
    font-size:14px;
    font-family:'Manrope', sans-serif;
    color:var(--ink);
    background:#fbfbfd;
    outline:none;
    transition:border-color .15s ease, box-shadow .15s ease;
  }
  .form-panel input:focus{
    border-color:var(--accent-500);
    box-shadow:0 0 0 3px rgba(91,111,242,0.15);
    background:#fff;
  }

  .btn-enter{
    width:100%;
    border:none;
    background:linear-gradient(135deg, var(--accent-500), #4a5adf);
    color:#fff;
    font-weight:700;
    font-size:13.5px;
    letter-spacing:0.3px;
    padding:13px;
    border-radius:9px;
    cursor:pointer;
    margin-top:6px;
    transition:filter .15s ease, transform .1s ease;
  }
  .btn-enter:hover{ filter:brightness(1.08); }
  .btn-enter:active{ transform:translateY(1px); }

  .row-between{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-top:14px;
    font-size:12.5px;
  }
  .row-between label{ display:flex; align-items:center; gap:6px; font-weight:500; color:#4b5063; margin:0; }
  .row-between a{ color:var(--accent-500); text-decoration:none; font-weight:600; }
  .row-between a:hover{ text-decoration:underline; }

  .divider{
    display:flex;
    align-items:center;
    gap:10px;
    margin:26px 0 16px;
    font-size:11.5px;
    color:var(--text-muted);
    text-transform:uppercase;
    letter-spacing:0.4px;
  }
  .divider::before, .divider::after{
    content:"";
    flex:1;
    height:1px;
    background:var(--border-soft);
  }

  .quick-access{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
  }
  .quick-btn{
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:6px;
    padding:14px 8px;
    border:1.5px solid var(--border-soft);
    border-radius:10px;
    background:#fff;
    cursor:pointer;
    text-align:center;
    transition:border-color .15s ease, background .15s ease;
  }
  .quick-btn:hover{ border-color:var(--accent-500); background:#f7f8fe; }
  .quick-btn .icon-wrap{
    width:36px; height:36px;
    border-radius:9px;
    display:flex; align-items:center; justify-content:center;
    background:#eef0fb;
  }
  .quick-btn strong{ font-size:12.5px; color:var(--ink); font-weight:700; }
  .quick-btn span{ font-size:11px; color:var(--text-muted); }

  .alert-error{
    background:#fdecec;
    border:1px solid #f6c2c2;
    color:#b3261e;
    font-size:12.5px;
    padding:10px 12px;
    border-radius:8px;
    margin-bottom:16px;
    text-align:center;
  }

  @media (max-width:760px){
    .auth-card{ grid-template-columns:1fr; }
    .brand-panel{ display:none; }
    .form-panel{ padding:36px 26px; }
  }
</style>
</head>
<body>

<div class="auth-card">

  <!-- Panel izquierdo: marca -->
  <div class="brand-panel">
    <div class="brand-mark">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
        <path d="M12 2L22 20H2L12 2Z" fill="url(#g1)"/>
        <defs>
          <linearGradient id="g1" x1="2" y1="2" x2="22" y2="20" gradientUnits="userSpaceOnUse">
            <stop stop-color="#7c8bf5"/>
            <stop offset="1" stop-color="#2dd4bf"/>
          </linearGradient>
        </defs>
      </svg>
      AURA<span class="light">SYNC</span>
    </div>

    <div class="brand-copy">
      <h1>BIENVENIDO</h1>
      <h2>Sistema de Control de Acceso</h2>
      <p>Gestión inteligente de entradas y salidas para el personal corporativo.</p>
    </div>

    <div class="brand-visual">
      <div class="glow"></div>
      <div class="ring"></div>
      <svg width="180" height="70" viewBox="0 0 180 70">
        <polyline points="0,50 20,42 40,46 60,28 80,34 100,18 120,24 140,10 160,16 180,4"
          fill="none" stroke="#2dd4bf" stroke-width="2"/>
        <rect x="10" y="55" width="6" height="10" fill="#7c8bf5" opacity="0.8"/>
        <rect x="30" y="48" width="6" height="17" fill="#7c8bf5" opacity="0.8"/>
        <rect x="50" y="40" width="6" height="25" fill="#7c8bf5" opacity="0.8"/>
        <rect x="70" y="45" width="6" height="20" fill="#7c8bf5" opacity="0.8"/>
        <rect x="90" y="30" width="6" height="35" fill="#7c8bf5" opacity="0.8"/>
        <rect x="110" y="35" width="6" height="30" fill="#7c8bf5" opacity="0.8"/>
        <rect x="130" y="20" width="6" height="45" fill="#7c8bf5" opacity="0.8"/>
      </svg>
    </div>
  </div>

  <!-- Panel derecho: formulario -->
  <div class="form-panel">

    <?php if (!empty($error)): ?>
      <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="field">
        <label for="usuario">USUARIO</label>
        <input type="text" id="usuario" name="usuario" placeholder="ej. gerencia o administracion" required autocomplete="off">
      </div>

      <div class="field">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required>
      </div>

      <button class="btn-enter" type="submit">INGRESAR AL SISTEMA</button>

      <div class="row-between">
        <label><input type="checkbox" name="recordarme"> Recordarme</label>
        <a href="#">¿Olvidaste tu contraseña?</a>
      </div>
    </form>

    <div class="divider">O usar acceso rápido</div>

    <div class="quick-access">
      <button type="button" class="quick-btn" onclick="alert('Función de huella dactilar próximamente.');">
        <div class="icon-wrap">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5b6ff2" stroke-width="1.8">
            <path d="M12 2a5 5 0 0 0-5 5v2a5 5 0 0 0 10 0V7a5 5 0 0 0-5-5Z"/>
            <path d="M7 12v1a5 5 0 0 0 10 0v-1M4 12v2a8 8 0 0 0 16 0v-2"/>
          </svg>
        </div>
        <strong>Huella Dactilar</strong>
        <span>Biometría</span>
      </button>

      <button type="button" class="quick-btn" onclick="alert('Función de escaneo QR próximamente.');">
        <div class="icon-wrap">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5b6ff2" stroke-width="1.8">
            <rect x="3" y="3" width="7" height="7"/>
            <rect x="14" y="3" width="7" height="7"/>
            <rect x="3" y="14" width="7" height="7"/>
            <path d="M14 14h3v3h-3zM19 14h2v2h-2zM14 19h2v2h-2zM19 19h2v2h-2z"/>
          </svg>
        </div>
        <strong>Código QR</strong>
        <span>Escanear QR</span>
      </button>
    </div>

  </div>
</div>

</body>
</html>