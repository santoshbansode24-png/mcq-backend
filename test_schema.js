const fetch = require('node-fetch'); // Native fetch is available in Node 18+

async function checkSchema() {
    try {
        const response = await fetch('https://api.veeruapp.in/backend/api/list_tables.php');
        const text = await response.text();
        console.log(text);
    } catch (e) {
        console.error(e);
    }
}
checkSchema();
