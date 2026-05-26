async function testUpload() {
    try {
        const formData = new FormData();
        formData.append('teacher_id', 1);
        formData.append('class_id', 1);
        formData.append('title', 'Test Upload');
        formData.append('message', 'This is a test');
        formData.append('update_type', 'announcement');
        
        const response = await fetch('https://api.veeruapp.in/backend/api/teacher/upload_class_material.php', {
            method: 'POST',
            body: formData
        });
        const text = await response.text();
        console.log("Upload Response:", text);
    } catch (error) {
        console.log("Error:", error);
    }
}

testUpload();
