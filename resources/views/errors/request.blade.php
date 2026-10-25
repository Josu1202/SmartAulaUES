<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>SmartAulaUES · {{ $status }}</title>
<style>body{margin:0;background:#f5f6f8;color:#20242a;font:1rem/1.6 system-ui,sans-serif}main{max-width:36rem;margin:12vh auto;padding:2rem;background:#fff;border-top:4px solid #b91625;border-radius:8px}h1{font-size:1.5rem}a{color:#a91623}nav{display:flex;gap:1.5rem;flex-wrap:wrap}@media(max-width:640px){main{margin:8vh 1rem;padding:1.5rem}}</style></head>
<body><main><strong>SmartAulaUES</strong><h1>No pudimos completar esta solicitud</h1><p>{{ $message }}</p><nav><a href="{{ url('/dashboard') }}">Ir al inicio</a><a href="{{ url('/login') }}">Iniciar sesión</a></nav></main></body>
</html>
