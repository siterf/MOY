const https = require('https');

const CONFIG = {
  MAX_TOKEN: 'f9LHodD0cOL0j98fJFPmRgDpV-IIo1xkYJKD-KGxg_DSBFo0mLgO9gA3eZIBTCtVmN8vz34dapUPnCJHtBrS',
  CHAT_ID: 331000658
};

const HEADERS = {
  'Content-Type': 'application/json; charset=utf-8',
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
  'Access-Control-Allow-Headers': 'Content-Type, X-Requested-With'
};

module.exports.handler = async function (event, context) {
  if (event.httpMethod === 'OPTIONS') {
    return { statusCode: 200, headers: HEADERS, body: '' };
  }

  if (event.httpMethod !== 'POST') {
    return { statusCode: 405, headers: HEADERS, body: JSON.stringify({ error: 'Method not allowed' }) };
  }

  try {
    let body = event.body || '{}';
    if (event.isBase64Encoded) {
      body = Buffer.from(body, 'base64').toString('utf8');
    }
    const data = typeof body === 'string' ? JSON.parse(body) : body;

    // Антиспам-ловушка
    if (data.website_trap) {
      return { statusCode: 200, headers: HEADERS, body: JSON.stringify({ success: true }) };
    }

    const clientName = (data.client_name || 'Не указано').trim();
    const contact = (data.contact_info || data.contact || 'Не указано').trim();
    const companyName = (data.company_name || 'Не указано').trim();
    const city = (data.city || '').trim();
    const services = (data.services || '').trim();
    const mapsLink = (data.maps_link || '').trim();
    const socialLink = (data.social_link || '').trim();
    const currentIssues = (data.current_issues || '').trim();
    const photoReady = (data.photo_ready || '').trim();
    const deadline = (data.deadline || '').trim();
    const comment = (data.comment || '').trim();
    const source = (data.source || 'Сайт (Бриф)').trim();

    let goalsText = '';
    if (data.goals) {
      if (Array.isArray(data.goals)) {
        goalsText = data.goals.join('\n• ');
      } else {
        goalsText = String(data.goals);
      }
    }

    let msg = '📋 *НОВЫЙ БРИФ С САЙТА*\n';
    msg += '━━━━━━━━━━━━━━━━━━━━━\n';
    msg += '👤 *Имя:* ' + clientName + '\n';
    msg += '📱 *Контакт:* ' + contact + '\n';
    msg += '🏢 *Бизнес:* ' + companyName + (city ? ' (' + city + ')' : '') + '\n';

    if (services) msg += '🎯 *Услуги:* ' + services + '\n';
    if (mapsLink) msg += '📍 *Карты:* ' + mapsLink + '\n';
    if (socialLink) msg += '🌐 *Соцсети/Сайт:* ' + socialLink + '\n';
    if (goalsText) msg += '\n🎯 *Задачи проекта:*\n• ' + goalsText + '\n';
    if (currentIssues) msg += '\n⚠️ *Что не устраивает:*\n' + currentIssues + '\n';
    if (photoReady) msg += '📸 *Фотоматериалы:* ' + photoReady + '\n';
    if (deadline) msg += '⏳ *Желаемые сроки:* ' + deadline + '\n';
    if (comment) msg += '\n💬 *Комментарий:* ' + comment + '\n';

    msg += '\n━━━━━━━━━━━━━━━━━━━━━\n';
    msg += '🌐 *Источник:* ' + source + '\n';
    msg += '🕒 *Время:* ' + new Date().toLocaleString('ru-RU', { timeZone: 'Europe/Moscow' });

    // Отправка в MAX
    await sendToMax(msg);

    return {
      statusCode: 200,
      headers: HEADERS,
      body: JSON.stringify({ success: true, message: 'Бриф отправлен в MAX' })
    };
  } catch (err) {
    console.error('Handler error:', err);
    return {
      statusCode: 500,
      headers: HEADERS,
      body: JSON.stringify({ success: false, error: err.message })
    };
  }
};

function sendToMax(text) {
  return new Promise((resolve, reject) => {
    const payload = JSON.stringify({
      text,
      format: 'markdown'
    });

    const options = {
      hostname: 'platform-api2.max.ru',
      path: '/messages?chat_id=' + CONFIG.CHAT_ID,
      method: 'POST',
      headers: {
        'Authorization': CONFIG.MAX_TOKEN,
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(payload, 'utf8'),
        'User-Agent': 'NodeJS/YandexCloudFunction'
      },
      rejectUnauthorized: false
    };

    const req = https.request(options, res => {
      let d = '';
      res.on('data', c => d += c);
      res.on('end', () => {
        if (res.statusCode >= 200 && res.statusCode < 300) {
          resolve(d);
        } else {
          console.error('MAX API error status:', res.statusCode, d);
          resolve(d); // resolve to not fail client
        }
      });
    });

    req.on('error', err => {
      console.error('MAX request error:', err.message);
      resolve(null);
    });

    req.write(payload);
    req.end();
  });
}
