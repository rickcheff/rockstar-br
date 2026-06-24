const http = require('http');
const fs = require('fs');
const path = require('path');
const url = require('url');

const PORT = 3500;

// Armazenar PIXs gerados (em memória)
const payments = {};

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
        console.log('Raw body:', body);
        if (!body || body.trim() === '') {
          res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ success: false, erro: 'Dados inválidos. Body vazio.' }));
          return;
        }

        const data = JSON.parse(body);
        console.log('PIX Request:', data);

        // Aceita tanto 'value' quanto 'valor'
        const valueStr = data.value || data.valor || '0';
        const valueInReais = parseFloat(valueStr) || 0;
        const valueInCents = Math.round(valueInReais * 100);

        console.log('Value conversion:', { valueStr, valueInReais, valueInCents });

        // Simular resposta Mangofy com código PIX realista
        const txId = 'PIX' + Date.now() + Math.random().toString(36).substring(2, 8).toUpperCase();

        // Código PIX simulado (normalmente seria um string muito longo do QR code)
        const pixCode = '00020126580014br.gov.bcb.pix0136' +
                       'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' +
                       '52040000530398654061' +
                       valueInCents.toString().padStart(10, '0') +
                       '5303986540612345678901234567890123456789012345678901234567';

        const response = {
          success: true,
          sucesso: true,
          payment_code: txId,
          status: 'pending',
          valor: valueInCents,
          pix_copia_cola: pixCode,
          pix_qrcode_url: `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(pixCode)}`,
          data: {
            id: txId,
            transactionId: txId,
            valor: valueInReais.toFixed(2),
            copiaecola: pixCode,
            qrcode_image: `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(pixCode)}`,
            status: 'pending',
            pix_code: pixCode,
            customer: {
              name: data.nome || data.name || 'N/A',
              email: data.email || 'N/A',
              cpf: data.cpf || data.document || 'N/A',
              phone: data.telefone || data.phone || 'N/A'
            }
          }
        };

        // Armazenar o pagamento como "pending"
        payments[txId] = {
          status: 'pending',
          createdAt: Date.now(),
          data: data
        };

        console.log('PIX Response:', response);
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify(response, null, 2));
      } catch (e) {
        console.error('Erro ao processar PIX:', e.message);
        res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify({
          success: false,
          erro: 'Dados inválidos. Erro: ' + e.message
        }));
      }
    });
    return;
  }

  // API Verificar
  if (pathname === '/gta/api-pix/verificar.php' && req.method === 'GET') {
    const code = parsedUrl.query.code || parsedUrl.query.id;
    console.log('Check payment:', code);

    // Consultar o status do pagamento armazenado
    let status = 'pending';

    if (payments[code]) {
      // Se o pagamento foi gerado há mais de 10 segundos, simular como "paid"
      const ageInSeconds = (Date.now() - payments[code].createdAt) / 1000;
      if (ageInSeconds > 10) {
        // 70% de chance de estar pago após 10 segundos
        status = Math.random() < 0.7 ? 'paid' : 'pending';
      } else {
        status = 'pending';
      }
      // Atualizar o status armazenado
      payments[code].status = status;
    } else {
      // Se não encontrou, retornar como pending
      status = 'pending';
    }

    const response = {
      success: true,
      data: {
        id: code,
        transactionId: code,
        status: status
      }
    };

    console.log('Payment status:', response.data.status, '(Age:', payments[code] ? Math.round((Date.now() - payments[code].createdAt) / 1000) + 's' : '?' + ')');
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
