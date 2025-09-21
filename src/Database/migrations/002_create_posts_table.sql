-- Create posts table
CREATE TABLE IF NOT EXISTS posts (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content TEXT NOT NULL,
    description TEXT,
    author VARCHAR(100) NOT NULL DEFAULT 'Kimhoon Rin',
    
    -- Image properties
    image_src VARCHAR(500),
    image_alt VARCHAR(255),
    image_position_x VARCHAR(10) DEFAULT '50%',
    image_position_y VARCHAR(10) DEFAULT '50%',
    
    -- Post metadata
    tags JSONB DEFAULT '[]',
    status VARCHAR(20) DEFAULT 'draft' CHECK (status IN ('draft', 'published')),
    
    -- Dates
    pub_date TIMESTAMP,
    updated_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_posts_slug ON posts(slug);
CREATE INDEX IF NOT EXISTS idx_posts_status ON posts(status);
CREATE INDEX IF NOT EXISTS idx_posts_pub_date ON posts(pub_date);
CREATE INDEX IF NOT EXISTS idx_posts_author ON posts(author);

-- Create GIN index for JSON tags for faster tag searches
CREATE INDEX IF NOT EXISTS idx_posts_tags ON posts USING gin(tags jsonb_path_ops);

-- Create trigger to automatically update updated_at
DROP TRIGGER IF EXISTS update_posts_updated_at ON posts;
CREATE TRIGGER update_posts_updated_at 
    BEFORE UPDATE ON posts 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

-- Insert sample post based on your example
INSERT INTO posts (title, slug, content, description, author, image_src, image_alt, image_position_x, image_position_y, tags, status, pub_date, updated_date) 
VALUES (
    'A Winter Ski Adventure at Ryuoo Ski Park',
    'ryuoo-ski-park',
    '# A Winter Ski Adventure at Ryuoo Ski Park

On **January 27, 2024**, I went on a ski trip to **Ryuoo Ski Park** in Nagano with my friend. It was a **one-day trip**, meaning we left in the morning and returned in the evening. We took a **highway bus** from **Tokyo** (Shinjuku station) to **Ryuoo**, and we had booked everything in advance—a week before the trip. This included our **ski clothes, snowboard, and bus tickets**.

## The Journey Begins

The journey to Ryuoo Ski Park was filled with excitement and anticipation. As we traveled through the Japanese countryside, the landscape gradually transformed from urban cityscape to snow-covered mountains.

## Skiing Experience

The ski park offered breathtaking views and challenging slopes perfect for both beginners and experienced skiers. The powder snow was exceptional, providing an unforgettable skiing experience.

## Memorable Moments

Throughout the day, we encountered many friendly locals and fellow ski enthusiasts, making the experience even more special. The combination of great weather, excellent snow conditions, and good company made this trip truly memorable.',
    'A first-time ski adventure at Ryuoo Ski Park in Nagano, full of skiing challenges, amazing views, and unforgettable encounters.',
    'Kimhoon Rin',
    '/images/ryuoo/main.jpg',
    'Skiing at Ryuoo Ski Park',
    '50%',
    '20%',
    '["Ryuoo", "Skiing", "Travel", "Japan", "Adventure"]'::jsonb,
    'published',
    '2024-01-27T00:00:00.000Z',
    '2024-01-27T00:00:00.000Z'
)
ON CONFLICT (slug) DO NOTHING;