#!/bin/bash
# Script to update file headers from "MCQ Project 2.0" to "Veeru"

# List of admin files to update
files=(
    "c:/xampp/htdocs/mcq project2.0/backend/admin/videos.php"
    "c:/xampp/htdocs/mcq project2.0/backend/admin/users.php"
    "c:/xampp/htdocs/mcq project2.0/backend/admin/subjects.php"
    "c:/xampp/htdocs/mcq project2.0/backend/admin/quick_revision.php"
    "c:/xampp/htdocs/mcq project2.0/backend/admin/notes.php"
    "c:/xampp/htdocs/mcq project2.0/backend/admin/mcqs.php"
    "c:/xampp/htdocs/mcq project2.0/backend/admin/logout.php"
    "c:/xampp/htdocs/mcq project2.0/backend/admin/flashcards.php"
    "c:/xampp/htdocs/mcq project2.0/backend/admin/dashboard.php"
    "c:/xampp/htdocs/mcq project2.0/backend/admin/classes.php"
    "c:/xampp/htdocs/mcq project2.0/backend/admin/chapters.php"
)

echo "Updating file headers to Veeru branding..."

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        # Use sed to replace "MCQ Project 2.0" with "Veeru" in the file header (first 10 lines)
        sed -i '1,10s/MCQ Project 2\.0/Veeru/g' "$file"
        echo "✓ Updated: $file"
    else
        echo "✗ Not found: $file"
    fi
done

echo "Done!"
