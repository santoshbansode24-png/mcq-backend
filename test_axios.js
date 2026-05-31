const axios = require('axios');
const FormData = require('form-data');

async function test() {
    const formData = new FormData();
    formData.append('teacher_id', '1');
    formData.append('class_id', '1');
    formData.append('title', 'New Worksheet: Test');
    
    const payloadData = {
        type: 'worksheet_data',
        data: 'test payload',
        subjectNames: 'Math',
        textMessage: 'A new worksheet has been generated.'
    };
    
    formData.append('payload', JSON.stringify(payloadData));
    formData.append('message', 'A new worksheet has been generated.');
    formData.append('update_type', 'worksheet');

    try {
        const res = await axios.post('http://127.0.0.1/veeru/backend/api/teacher/upload_class_material.php', formData, {
            headers: formData.getHeaders()
        });
        console.log("SUCCESS:", res.data);
    } catch (e) {
        console.log("ERROR:", e.response ? e.response.data : e.message);
    }
}
test();
