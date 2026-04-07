from PIL import Image
import numpy as np

def remove_background(input_path, output_path):
    img = Image.open(input_path).convert("RGBA")
    data = np.array(img)
    
    # Extract RGB channels
    r, g, b, a = data.T
    
    # Find white/gray checkerboard pixels
    # We'll make anything > 200 transparent, or since we know it's a typical checkerboard
    # let's be more precise: the boy's boots are brown, hair black, clothes blue/orange
    # So any pixel that is gray/white (R~=G~=B > 180) can be removed
    
    is_gray = (abs(r.astype(int) - g.astype(int)) < 15) & (abs(g.astype(int) - b.astype(int)) < 15)
    is_light = (r > 180) & (g > 180) & (b > 180)
    
    # For a checkerboard baked in, the white and grey parts both satisfy this
    white_areas = is_gray & is_light
    
    data[..., :-1][white_areas.T] = (0, 0, 0)
    data[..., -1][white_areas.T] = 0
    
    img2 = Image.fromarray(data)
    img2.save(output_path)
    print("Successfully removed background using thresholding!")

if __name__ == "__main__":
    import sys
    input_file = "C:/xampp/htdocs/veeru/student_app/assets/veeru_splash_transparent.png"
    output_file = "C:/xampp/htdocs/veeru/student_app/assets/veeru_splash_transparent.png" # Overwrite
    remove_background(input_file, output_file)
