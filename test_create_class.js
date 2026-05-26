async function testCreateClass() {
    try {
        const response = await fetch('https://api.veeruapp.in/backend/api/teacher/create_classroom.php', {
            method: 'POST',
            body: JSON.stringify({
                teacher_id: 1, // Assuming teacher ID 1 exists
                class_id: 1, // Assuming class ID 1 exists
                division_name: 'A'
            }),
            headers: {
                'Content-Type': 'application/json'
            }
        });
        const text = await response.text();
        console.log("Create Class Response:", text);
    } catch (error) {
        console.log("Error:", error);
    }
}

testCreateClass();
