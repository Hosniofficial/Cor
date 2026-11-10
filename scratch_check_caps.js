const fs = require('fs');
const content = fs.readFileSync('web.elkaisar.com/js-3.4.113/obfuscated.js', 'utf8');

const regex = /army[_\s]*cap/gi;
let m;
while ((m = regex.exec(content)) !== null) {
    console.log('Match at ' + m.index + ':', content.substring(Math.max(0, m.index - 50), Math.min(content.length, m.index + 100)).replace(/\n/g, ' '));
}
