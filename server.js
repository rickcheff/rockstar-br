const http = require('http');
const fs = require('fs');
const path = require('path');
const url = require('url');

const PORT = 3500;

const server = http.createServer((req, res) => {
  const parsedUrl = url.parse(req.url, true);
  const pathname = parsedUrl.pathname;

  // CORS
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') {
    res.writeHead(200);
    res.end();
    return;
  }

  // API PIX
  if (pathname === '/gta/api-pix/api.php' && req.method === 'POST') {
    let body = '';
    req.on('data', chunk => {
      body += chunk.toString();
    });
    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        console.log('PIX Request:', data);

        // Simular resposta Mangofy com código PIX realista
        const txId = 'PIX' + Date.now() + Math.random().toString(36).substring(2, 8).toUpperCase();

        // Código PIX simulado (normalmente seria um string muito longo do QR code)
        const pixCode = '00020126580014br.gov.bcb.pix0136' +
                       'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' +
                       '52040000530398654061' +
                       Math.round(data.value * 100).toString().padStart(10, '0') +
                       '5303986540612345678901234567890123456789012345678901234567';

        const response = {
          success: true,
          sucesso: true,
          payment_code: txId,
          status: 'pending',
          valor: Math.round(data.value * 100),
          data: {
            id: txId,
            transactionId: txId,
            valor: data.value,
            status: 'pending',
            pix_code: pixCode
          }
        };

        console.log('PIX Response:', response);
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify(response, null, 2));
      } catch (e) {
        console.error('Erro ao processar PIX:', e);
        res.writeHead(400, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: false, erro: 'Dados inválidos' }));
      }
    });
    return;
  }

  // API Verificar
  if (pathname === '/gta/api-pix/verificar.php' && req.method === 'GET') {
    const code = parsedUrl.query.code || parsedUrl.query.id;
    console.log('Check payment:', code);

    const response = {
      success: true,
      data: {
        id: code,
        transactionId: code,
        status: 'paid'
      }
    };

    res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify(response));
    return;
  }

  // Arquivos estáticos
  let filePath = path.join(__dirname, pathname);

  // Se for pasta, servir index.html
  if (pathname === '/') {
    filePath = path.join(__dirname, 'index.html');
  }

  fs.readFile(filePath, (err, content) => {
    if (err) {
      res.writeHead(404, { 'Content-Type': 'text/html; charset=utf-8' });
      res.end('<h1>404 - Arquivo não encontrado</h1>');
      return;
    }

    let contentType = 'text/html; charset=utf-8';
    if (filePath.endsWith('.js')) contentType = 'text/javascript; charset=utf-8';
    else if (filePath.endsWith('.css')) contentType = 'text/css; charset=utf-8';
    else if (filePath.endsWith('.json')) contentType = 'application/json; charset=utf-8';
    else if (filePath.endsWith('.png')) contentType = 'image/png';
    else if (filePath.endsWith('.jpg')) contentType = 'image/jpeg';
    else if (filePath.endsWith('.svg')) contentType = 'image/svg+xml';

    res.writeHead(200, { 'Content-Type': contentType });
    res.end(content);
  });
});

server.listen(PORT, 'localhost', () => {
  console.log(`\n✅ Servidor rodando em http://localhost:${PORT}\n`);
  console.log(`   Teste o checkout com PIX!\n`);
});
