from PIL import Image

def fix_adaptive_icon():
    print("Fixing adaptive icon...")
    try:
        # Open the original icon
        img = Image.open('icon.png').convert("RGBA")
        
        # Find the bounding box of non-transparent pixels
        # getbbox() works on the alpha channel if we use the alpha channel
        bbox = img.split()[-1].getbbox()
        
        if bbox:
            cropped_logo = img.crop(bbox)
        else:
            cropped_logo = img

        print(f"Cropped logo size: {cropped_logo.size}")
        
        # Adaptive icon foreground is 1080x1080
        # The "safe zone" diameter is 720px (66.66%)
        # To make it look perfectly fit without being "zoomed out", we want the logo to be about 700px in the longest dimension
        target_size = 720
        
        w, h = cropped_logo.size
        aspect_ratio = w / h
        
        if w > h:
            new_w = target_size
            new_h = int(target_size / aspect_ratio)
        else:
            new_h = target_size
            new_w = int(target_size * aspect_ratio)
            
        print(f"New logo size: {new_w}x{new_h}")
        resized_logo = cropped_logo.resize((new_w, new_h), Image.Resampling.LANCZOS)
        
        # Create a new transparent 1080x1080 image
        final_img = Image.new("RGBA", (1080, 1080), (0, 0, 0, 0))
        
        # Paste resized logo into center
        x_offset = (1080 - new_w) // 2
        y_offset = (1080 - new_h) // 2
        
        final_img.paste(resized_logo, (x_offset, y_offset), resized_logo)
        
        # Save as adaptive-foreground-fixed.png
        final_img.save('adaptive-foreground-fixed.png', 'PNG')
        print("Successfully created adaptive-foreground-fixed.png")
        
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    fix_adaptive_icon()
