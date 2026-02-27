from PIL import Image

def create_play_store_icon():
    # Open the adaptive foreground icon as it usually has a transparent background
    try:
        # We'll use the adaptive foreground backup, or icon.png
        # Let's try icon.png first, if it fails, try adaptive foreground
        img = Image.open('student_app/assets/icon-backup.png').convert("RGBA")
    except Exception as e:
        print(f"Failed to open icon-backup.png: {e}")
        try:
            img = Image.open('student_app/assets/icon.png').convert("RGBA")
        except Exception as e2:
            print(f"Failed to open icon.png: {e2}")
            return

    # Check if there's a white background and make it transparent for processing
    # Or just find the bounding box of the actual logo
    # Actually, if we just resize the existing logo down slightly into a white 512x512 canvas,
    # that should be enough.
    
    # Let's create a new white 512x512 image
    final_size = (512, 512)
    background = Image.new("RGBA", final_size, (255, 255, 255, 255))
    
    # We want the logo to take up at most 320x320 pixels (leaving ~96px padding on all sides)
    target_logo_size = 320

    # Let's get the bounding box of non-white pixels
    # Convert to grayscale and invert to find bounding box easily
    gray = img.convert("L")
    inverted = Image.eval(gray, lambda x: 255 - x)
    bbox = inverted.getbbox()
    
    if bbox:
        # Crop to the actual logo contents
        cropped_logo = img.crop(bbox)
    else:
        cropped_logo = img

    # Resize the cropped logo so its maximum dimension is target_logo_size
    w, h = cropped_logo.size
    aspect_ratio = w / h
    
    if w > h:
        new_w = target_logo_size
        new_h = int(target_logo_size / aspect_ratio)
    else:
        new_h = target_logo_size
        new_w = int(target_logo_size * aspect_ratio)
        
    resized_logo = cropped_logo.resize((new_w, new_h), Image.Resampling.LANCZOS)
    
    # Paste the resized logo onto the center of the white background
    x_offset = (512 - new_w) // 2
    y_offset = (512 - new_h) // 2
    
    # Use the resized logo itself as the mask if it has transparency, otherwise just paste
    if resized_logo.mode == 'RGBA':
        background.paste(resized_logo, (x_offset, y_offset), resized_logo)
    else:
        background.paste(resized_logo, (x_offset, y_offset))
        
    # Convert to RGB to save as PNG or JPG (Google Play recommends PNG)
    background = background.convert("RGB")
    background.save('play_store_icon_512.png', 'PNG')
    print("Successfully created play_store_icon_512.png")

if __name__ == "__main__":
    create_play_store_icon()
