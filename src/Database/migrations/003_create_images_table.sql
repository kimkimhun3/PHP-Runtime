-- Create images table for tracking uploaded files
CREATE TABLE IF NOT EXISTS images (
    id SERIAL PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    original_name VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    size INTEGER NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    width INTEGER,
    height INTEGER,
    
    -- Optional: link to post if this image belongs to a specific post
    post_id INTEGER REFERENCES posts(id) ON DELETE SET NULL,
    
    -- Image metadata
    alt_text VARCHAR(255),
    description TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_images_filename ON images(filename);
CREATE INDEX IF NOT EXISTS idx_images_post_id ON images(post_id);
CREATE INDEX IF NOT EXISTS idx_images_mime_type ON images(mime_type);
CREATE INDEX IF NOT EXISTS idx_images_created_at ON images(created_at);

-- Create trigger to automatically update updated_at
DROP TRIGGER IF EXISTS update_images_updated_at ON images;
CREATE TRIGGER update_images_updated_at 
    BEFORE UPDATE ON images 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();