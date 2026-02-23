const axios = require('axios');

async function testLogin() {
    try {
        console.log('Testing Reviewer Login...');
        const response = await axios.post('https://api.veeruapp.in/backend/api/login.php', {
            email: 'reviewer@veeru.com',
            password: 'veeru123'
        });
        console.log('Response Status:', response.status);
        console.log('Response Data:', JSON.stringify(response.data, null, 2));
    } catch (error) {
        console.error('Login Failed!');
        if (error.response) {
            console.error('Status:', error.response.status);
            console.error('Data:', JSON.stringify(error.response.data, null, 2));
        } else {
            console.error('Error:', error.message);
        }
    }
}

testLogin();
