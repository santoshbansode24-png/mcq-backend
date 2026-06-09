from PIL import Image

# Open the original raw image
img = Image.open(r"C:\Users\ADMIN\.gemini\antigravity\brain\6a0e8017-b258-4998-9b88-a2290601c6c7\feature_graphic_raw_1781035188130.png")

# Resize it to exactly 1024x500
img_resized = img.resize((1024, 500))

# Save it to the Veeru project folder where you can easily find it
img_resized.save(r"C:\xampp\htdocs\veeru\feature_graphic_1024x500.png")
print("Image resized successfully!")
