async function testGetClasses() {
    try {
        const response = await fetch('https://api.veeruapp.in/backend/api/teacher/get_classes.php', {
            method: 'POST',
            body: JSON.stringify({
                teacher_id: 1
            }),
            headers: {
                'Content-Type': 'application/json'
            }
        });
        const text = await response.text();
        console.log("get_classes Response:", text);
    } catch (error) {
        console.log("Error:", error);
    }
}

testGetClasses();
