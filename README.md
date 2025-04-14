# **Laravel Backend - Blog Management System**

## 📦 Project Overview

This is the backend API for a scalable Blog Management System built using **Laravel 12**. It offers secure, token-based (JWT) authentication, efficient Redis caching, queue-based email notifications, and blog scheduling. The API is designed to handle large datasets (200k+ blog posts) and supports filtering, full blog CRUD, and commenting functionality.

### Key Features

-   🧑‍💻 Passwordless JWT authentication for users
-   ✍️ Blog post creation with SEO metadata, scheduling, and image upload
-   💬 Commenting system with user association
-   ⚡ Redis caching for blog lists and blog details to improve performance
-   ✉️ Queued email notifications for newly published blogs
-   📅 Blog scheduling with `scheduled_at` and `published_at` logic
-   🧪 Fully seeded test data: 200k blog posts, 10 users, and comments

The backend exposes a REST API to be consumed by a separate Vue.js SPA [frontend](https://github.com/sohailnoor77/blog-platform-frontend).

## 🚀 Setup Instructions

### Prerequisites

-   PHP >= 8.1
-   Composer
-   MySQL / MariaDB
-   Redis

### Installation

```
git clone <backend-repo-url>
cd blogs-api
cp .env.example .env
composer install
php artisan key:generate
```

### Configure .env

Set up your database and Redis settings:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db
DB_USERNAME=root
DB_PASSWORD=

REDIS_CLIENT=predis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### Migrate and Seed

```
php artisan migrate:fresh --seed
```

This will:

-   Create tables for users, blogs, and blog_comments
-   Seed 10 users (including Sohail Noor)
-   Generate 200,000 blogs with 2-5 comments each

### Run Server

```
php artisan serve
```

### Run Queue Worker

```
php artisan queue:work
```

## 💡 Features Implemented

-   JWT Auth (passwordless login via token)
-   Blog CRUD
-   Scheduled blog publishing with email notifications (queued)
-   Comment system (authenticated)
-   Redis caching for:
-   Blog list
-   Blog detail
-   Search results
-   Pagination with infinite scroll
-   Users can schedule blog posts for future publishing using the `scheduled_at` field. A Laravel job checks the schedule and publishes accordingly.

## 🗃️ Database Schema

### Users Table

-   `id` (integer, primary key)
-   `name` (string)
-   `email` (string, unique)
-   `password` (string, hashed)
-   `created_at` / `updated_at` (timestamps)

### Blogs Table

-   `id` (integer, primary key)
-   `user_id` (foreign key to users)
-   `title` (string)
-   `excerpt` (string)
-   `description` (text)
-   `image` (string - URL or path)
-   `keywords` (JSON - array of strings)
-   `meta_title` (string)
-   `meta_description` (string)
-   `published_at` (timestamp, nullable)
-   `scheduled_at` (timestamp, nullable) ✅
-   `created_at` / `updated_at` (timestamps)

### Blog Comments Table

-   `id` (integer, primary key)
-   `user_id` (foreign key to users)
-   `blog_id` (foreign key to blogs)
-   `body` (text)
-   `created_at` / `updated_at` (timestamps)

## 🔥 Redis Caching Strategy

-   Blog list pages:

```
blogs:user:{user_id}:page:{page_number}
```

-   Homepage (public):

```
blogs_page:{page_number}
blogs_search:{slugified_search_term}:page:{page_number}
```

-   Blog detail:

```
blog:{blog_id}
```

-   Cache TTL: 10 minutes
-   Cache invalidated on create/update/delete of blog

## 📨 Queue System

-   Used for email notifications when blogs are published
-   Configured using Laravel's database queue
-   Use php artisan queue:work to process

## ⚙️ Additional Notes

-   Laravel factories are used to seed 200k blog records
-   Realistic dummy content generated with Faker
-   Public images used from [unsplash](https://unsplash.com/)
-   Tag-based keyword search with partial matches
-   Optimized blog list query with select + withCount

## 📂 Directory Structure Highlights

-   app/Http/Controllers/Api/: API routes and logic
-   app/Jobs/SendPostPublishedEmail.php: Queued email job
-   routes/api.php: All API routes

## 🧠 Decisions and Challenges

-   Chose Redis over Elasticsearch for speed and simplicity
-   Faced timeout and memory issues during seeding; solved with chunked inserts
-   Used cache keys carefully to allow search and pagination without collisions
-   Ensured cache invalidation on any content change

## 🔗 Frontend

-   The Vue 3 SPA is hosted in a separate repository
-   CORS and API token support enabled
