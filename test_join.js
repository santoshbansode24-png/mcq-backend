async function testJoin() {
    try {
        const response = await fetch('https://api.veeruapp.in/backend/api/student/join_classroom.php', {
            method: 'POST',
            body: JSON.stringify({
                student_id: 8,
                class_code: "BJVPMU"
            }),
            headers: {
                'Content-Type': 'application/json'
            }
        });
        const text = await response.text();
        console.log("Response text:", text);
    } catch (error) {
        console.log("Error:", error);
    }
}

testJoin();
