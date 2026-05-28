async function testJoin() {
    try {
        const response = await fetch('https://api.veeruapp.in/backend/api/student/join_classroom.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                student_id: 1,
                class_code: 'ODGQVB'
            })
        });
        const text = await response.text();
        console.log('Status:', response.status);
        console.log('Response:', text);
    } catch (error) {
        console.error('Network Error:', error.message);
    }
}

testJoin();
