## API Endpoints Summary
🔓 Public Endpoints (No Authentication Required)
Health & Info
GET    /api/health              # API health check
GET    /api/stats               # Blog statistics (post count, etc.)


Posts (Public View)
GET    /api/posts               # Get all published posts (with pagination)
                                # Query params: ?page=1&limit=10&search=query

GET    /api/posts/{slug}        # Get single published post by slug
                                # Example: /api/posts/ryuoo-ski-park

GET    /api/posts/tags          # Get all available tags

GET    /api/posts/tag/{tag}     # Get published posts by tag
                                # Example: /api/posts/tag/Skiing

File Serving
GET    /uploads/{filename}      # Serve uploaded images/files
                                # Example: /uploads/image.jpg

🔐 Authentication Endpoints
POST   /api/auth/login          # Admin login
                                # Body: {"email":"admin@blog.com","password":"admin"}

POST   /api/auth/logout         # Admin logout

GET    /api/auth/verify         # Verify JWT token (requires token)

POST   /api/auth/refresh        # Refresh JWT token (requires token)


🛡️ Protected Admin Endpoints (JWT Token Required)
Headers Required: Authorization: Bearer YOUR_JWT_TOKEN
Post Management
GET    /api/admin/posts         # Get all posts (including drafts)
                                # Query params: ?page=1&limit=10

GET    /api/admin/posts/{id}    # Get single post by ID for editing
                                # Example: /api/admin/posts/1

POST   /api/admin/posts         # Create new post
                                # Body: {title, content, description, status, tags, etc.}

PUT    /api/admin/posts/{id}    # Update existing post
                                # Example: PUT /api/admin/posts/1

DELETE /api/admin/posts/{id}    # Delete post
                                # Example: DELETE /api/admin/posts/1

PATCH  /api/admin/posts/{id}/publish  # Toggle publish/draft status
                                      # Example: PATCH /api/admin/posts/1/publish

File Management

POST   /api/admin/upload        # Upload image(s)
                                # Multipart form data with file upload

DELETE /api/admin/upload/{filename}  # Delete uploaded file
                                     # Example: DELETE /api/admin/upload/image.jpg