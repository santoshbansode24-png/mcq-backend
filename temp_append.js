
export const deleteClass = async (classId) => {
    try {
        const teacherData = await getTeacherData();
        if (!teacherData) {
            throw new Error('Teacher not logged in');
        }

        const response = await api.post('/teacher/delete_class.php', {
            teacher_id: teacherData.user_id,
            class_id: classId,
        });

        if (response.data.status === 'success') {
            return { success: true, message: response.data.message };
        } else {
            return { success: false, message: response.data.message || 'Failed to delete class' };
        }
    } catch (error) {
        console.error('Delete class error:', error);
        return { success: false, message: error.message || 'Network error' };
    }
};
