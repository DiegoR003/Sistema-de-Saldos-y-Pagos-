<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banana Group - Login </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<style>
    
body{
    background-color: #87CEFA;
}

h1{
    color: #fff;
    font-family: "arial", serif;
 }

    /* Reset & base */
* { box-sizing: border-box; }
html, body { height: 100%; }
body {
  margin: 0;
  font-family: 'Nunito', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, sans-serif;
  color: #2a2a2a;
  background: linear-gradient(180deg, #2f86c1 0%, #2a7ab1 40%, #2a7ab1 100%);
}

/* Layout */
.page {
  min-height: 100%;
  display: grid;
  place-items: center;
  padding: 200px 16px; /* espacio entre titulo y form */
  
}

.brand {
  color: #e9f4ff;
  font-weight: 700;
  font-size: clamp(28px, 4vw, 48px);
  margin: 0;
  letter-spacing: 0.5px;
}

/* Card */
.card {
  position: relative;
  width: min(520px, 92vw);
  background: #fff;
  border-radius: 4px;
  box-shadow: 0 10px 24px rgba(0,0,0,.15);
  padding: 22px 28px 26px;
}

.card-topbar {
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 6px;
  background: #22a6f2;
  border-top-left-radius: 4px;
  border-top-right-radius: 4px;
}

.card-title {
  text-align: center;
  font-size: 16px;
  color: #6b6f72;
  font-weight: 600;
  margin: 8px 0 14px;
}

/* Form fields */
.form { display: grid; gap: 12px; }

.field {
  position: relative;
  display: block;
}

.field input {
  width: 100%;
  padding: 12px 44px 12px 12px; /* espacio para el icono */
  border: 1px solid #d7dbe0;
  border-radius: 4px;
  outline: none;
  font-size: 14px;
  transition: box-shadow .2s ease, border-color .2s ease;
}

.field input::placeholder { color: #b2b6bb; }

.field input:focus {
  border-color: #22a6f2;
  box-shadow: 0 0 0 3px rgba(34,166,242,.15);
}

/* Icono a la derecha */
.with-icon-right .icon {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  width: 22px; height: 22px;
  display: inline-grid;
  place-items: center;
  pointer-events: none;
  color: #7d8a97;
}

.with-icon-right .icon svg {
  width: 20px; height: 20px;
  fill: currentColor;
}

/* Botón */
.btn {
  display: block;
  width: 100%;
  border: none;
  border-radius: 3px;
  padding: 12px 14px;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  background: #2b7fb8;
  cursor: pointer;
  margin-top: 10px;
  transition: filter .2s ease, transform .02s ease-in;
}

.btn:hover { filter: brightness(1.05); }
.btn:active { transform: translateY(1px); }

/* A11y */
.sr-only {
  position: absolute !important;
  height: 1px; width: 1px;
  overflow: hidden;
  clip: rect(1px,1px,1px,1px);
  white-space: nowrap;
}


/* Responsive */
@media (max-width: 420px) {
  .card-title { font-size: 15px; }
  .btn { padding: 12px; }
  .page{ padding: 250px 16px;   }
}

   </style>
<body>
    <main class="page">
    <h1 class="brand">Sistema Pagos</h1>

    <section class="card" role="dialog" aria-labelledby="card-title">
      <div class="card-topbar"></div>
      <h2 id="card-title" class="card-title">Iniciar Sesión</h2>

      <form class="form" action="#" method="post" novalidate>
        <label class="field with-icon-right">
          <span class="sr-only">Usuario o correo</span>
          <input type="text" name="user" autocomplete="username" placeholder="Correo Electrónico" />
          <span class="icon" aria-hidden="true">
            <!-- envelope icon -->
            <svg viewBox="0 0 24 24" width="20" height="20">
              <path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5L4 8V6l8 5 8-5v2z" />
            </svg>
          </span>
        </label>

        <label class="field with-icon-right">
          <span class="sr-only">Contraseña</span>
          <input type="password" name="password" autocomplete="current-password" placeholder="Contraseña" />
          <span class="icon" aria-hidden="true">
            <!-- lock icon -->
            <svg viewBox="0 0 24 24" width="20" height="20">
              <path d="M12 2a5 5 0 0 0-5 5v3H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V11a1 1 0 0 0-1-1h-2V7a5 5 0 0 0-5-5zm3 8H9V7a3 3 0 1 1 6 0v3z" />
            </svg>
          </span>
        </label>

        <button class="btn" type="submit">Iniciar Sesion</button>
      </form>
    </section>
  </main>
     
</body>
</html>