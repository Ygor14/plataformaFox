<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Redirecionando para o cadastro...</title>
  <script>
    // Pega o parâmetro ?code= da URL
    const params = new URLSearchParams(window.location.search);
    const code = params.get("code");

    // Caminho esperado pela aplicação SPA (ajuste conforme necessário)
    const redirectPath = code
      ? `/#/register?code=${code}`  // use /#/ se sua SPA usa hash routing
      : `/#/register`;

    // Redireciona o usuário
    window.location.replace(redirectPath);
  </script>
</head>
<body>
  <p>Redirecionando para o cadastro...</p>
</body>
</html>
