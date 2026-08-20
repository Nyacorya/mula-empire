<?php
// ==========================================
// Database Configuration for Mula Empire (Public UI)
// ==========================================
error_reporting(E_ALL);
ini_set('display_errors', 0);  

require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Check if SITE_ID is defined – if not, stop
if (!defined('SITE_ID')) {
    die('ERROR: SITE_ID is not defined in config.php');
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT affiliate_link, email, whatsapp_number FROM sites WHERE id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// FIX: assign constant to a variable before binding
$site_id = SITE_ID;
$stmt->bind_param("i", $site_id);

if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Fallback values
    $affiliate_link = 'https://test1.com';
    $email = 'test1@gmail.com';
    $whatsapp_number = '+1234567890';
    $whatsapp_group_link = 'https://chat.whatsapp.com/';
} else {
    $row = $result->fetch_assoc();
    $affiliate_link = $row['affiliate_link'];
    $email = $row['email'];
    $whatsapp_number = $row['whatsapp_number'];
    $whatsapp_group_link = '#';
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mula Empire Solutions – Earn, Grow, Thrive Online</title>
    
    <!-- ========== PRIMARY META TAGS ========== -->
    <title>Mula Empire Solutions — Premium Digital & IT Services</title>
    <meta name="description" content="Mula Empire Solutions is the ultimate global online earning platform where anyone can earn money by completing simple tasks. Empowering people worldwide to turn everyday online activities into real income and achieve financial freedom through secure and rewarding digital opportunities." />
    <meta name="keywords" content="IT solutions, digital transformation, software development, IT consulting, cloud services, cybersecurity, Mula Empire" />
    <meta name="robots" content="index, follow" />
    <meta name="author" content="Mula Empire Solutions" />
    <meta name="language" content="English" />
    <link rel="canonical" href="https://mula-empire.com/" />

    <!-- ========== OPEN GRAPH / SOCIAL META TAGS ========== -->
    <meta property="og:title" content="Mula Empire Solutions — Turn Your Time into Treasure" />
    <meta property="og:description" content="Empowering businesses with innovative IT solutions, digital strategy, and custom software development." />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://mula-empire.com/" />
    <meta property="og:image" content="https://mula-empire.com/img/og-image.jpg" />
    <meta property="og:site_name" content="Mula Empire Solutions" />
    <meta property="og:locale" content="en_US" />

    <!-- ========== TWITTER CARD META TAGS ========== -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Mula Empire Solutions — Digital & IT Excellence" />
    <meta name="twitter:description" content="Empowering businesses with innovative IT solutions, digital strategy, and custom software development." />
    <meta name="twitter:image" content="https://mula-empire.com/img/og-image.jpg" />
    <meta name="twitter:site" content="@mulaempire" />
    <meta name="twitter:creator" content="@mulaempire" />
    
    <link rel="icon" type="image/jpeg" href="https://mula-empire.com/img/og-image.jpg">

    <!-- ========== JSON-LD STRUCTURED DATA ========== -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Organization",
            "name": "Mula Empire Solutions",
            "legalName": "Mula Empire Solutions Ltd",
            "url": "https://mula-empire.com/",
            "logo": "https://mula-empire.com/assets/logo.png",
            "description": "Mula Empire Solutions delivers world-class IT consulting, digital transformation, and custom software development.",
            "email": "<?php echo htmlspecialchars($email); ?>",
            "telephone": "<?php echo htmlspecialchars($whatsapp_number); ?>",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "123 Tech Park, Suite 400",
                "addressLocality": "Silicon Valley",
                "addressRegion": "CA",
                "postalCode": "94043",
                "addressCountry": "USA"
            },
            "contactPoint": {
                "@type": "ContactPoint",
                "contactType": "Sales",
                "email": "<?php echo htmlspecialchars($email); ?>",
                "telephone": "<?php echo htmlspecialchars($whatsapp_number); ?>",
                "availableLanguage": ["English", "French"]
            },
            "foundingDate": "2025-06-15",
            "numberOfEmployees": 85,
            "industry": "Information Technology & Services",
            "taxID": "12-3456789"
        }
    </script>

    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "WebSite",
            "name": "Mula Empire Solutions",
            "url": "https://mula-empire.com/",
            "description": "Premium IT consulting, digital transformation, and custom software development services.",
            "potentialAction": {
                "@type": "SearchAction",
                "target": "https://mula-empire.com/search?q={search_term_string}",
                "query-input": "required name=search_term_string"
            }
        }
    </script>

    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Service",
            "serviceType": "IT Consulting & Digital Transformation",
            "provider": {
                "@type": "Organization",
                "name": "Mula Empire Solutions"
            },
            "areaServed": {
                "@type": "Country",
                "name": "United States"
            },
            "hasOfferCatalog": {
                "@type": "OfferCatalog",
                "name": "Mula Empire Services",
                "itemListElement": [{
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Custom Software Development",
                        "description": "Tailored software solutions built for your unique business needs."
                    }
                }, {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Cloud & Infrastructure",
                        "description": "Scalable cloud solutions and infrastructure management."
                    }
                }, {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Cybersecurity",
                        "description": "Enterprise-grade security assessments and implementation."
                    }
                }, {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Data Analytics & AI",
                        "description": "Actionable insights through advanced analytics and AI."
                    }
                }]
            }
        }
    </script>
      <link rel="manifest" href="/manifest.json">
      <link rel="icon" href="/img/favicon.ico">
      <link rel="apple-touch-icon" href="/img/logo.png">
      <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
      <link rel="stylesheet" href="assets/css/chat-btn.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">    
    <style>
        /* =========================================================
           MALI WAVE – Professional Blue/Teal Theme
           ========================================================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #0B1120;
            font-family: 'Inter', sans-serif;
            color: #E2E8F0;
            line-height: 1.6;
            scroll-behavior: smooth;
            overflow-x: hidden;
        }

        /* subtle animated background */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 30%, rgba(56, 189, 248, 0.05) 0%, transparent 50%),
                        radial-gradient(circle at 80% 70%, rgba(129, 140, 248, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #1A2332;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #38BDF8, #818CF8);
            border-radius: 12px;
        }

        h1, h2, h3, h4 {
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        /* Glassmorphism with blue accent */
        .glass-card {
            background: rgba(15, 23, 42, 0.88);
            backdrop-filter: blur(6px);
            border: 1px solid rgba(56, 189, 248, 0.2);
            border-radius: 32px;
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.5);
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }
        .glass-card:hover {
            border-color: rgba(56, 189, 248, 0.5);
            box-shadow: 0 25px 50px -12px rgba(56, 189, 248, 0.15);
            transform: translateY(-4px);
        }

        /* Buttons – blue gradient */
        .btn-gradient {
            background: linear-gradient(135deg, #38BDF8, #818CF8);
            border: none;
            color: #0B1120;
            padding: 12px 32px;
            border-radius: 60px;
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.01em;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(56, 189, 248, 0.3);
        }
        .btn-gradient:hover {
            background: linear-gradient(135deg, #60C7FF, #9CA3F8);
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 25px rgba(56, 189, 248, 0.5);
            color: #0B1120;
        }
        .btn-gradient.btn-sm {
            padding: 6px 20px;
            font-size: 0.8rem;
        }
        .btn-outline-light-custom {
            border: 2px solid #38BDF8;
            background: transparent;
            color: #38BDF8;
            border-radius: 60px;
            padding: 10px 26px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-outline-light-custom:hover {
            background: #38BDF8;
            color: #0B1120;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(56, 189, 248, 0.3);
        }

        /* Navbar */
        .navbar-custom {
            padding: 1rem 2rem;
            background: rgba(11, 17, 32, 0.88);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(56, 189, 248, 0.15);
        }
        .logo {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #38BDF8, #818CF8);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 10px rgba(56, 189, 248, 0.15);
        }

        /* Hero */
        .hero {
            padding: 70px 20px 90px 20px;
            text-align: center;
            position: relative;
        }
        .rating-badge {
            background: rgba(56, 189, 248, 0.12);
            border: 1px solid rgba(56, 189, 248, 0.3);
            display: inline-block;
            padding: 8px 22px;
            border-radius: 60px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #BAE6FD;
            margin-bottom: 1.2rem;
            backdrop-filter: blur(4px);
        }
        .hero h1 {
            font-size: 3.6rem;
            font-weight: 800;
            background: linear-gradient(to right, #FFFFFF, #BAE6FD, #38BDF8);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 1rem;
        }
        .hero p {
            color: #94A3B8;
            font-size: 1.25rem;
            max-width: 700px;
            margin: 0 auto 2rem auto;
            font-weight: 400;
        }
        .hero img {
            border-radius: 32px;
            box-shadow: 0 30px 50px -20px black;
            border: 1px solid rgba(56, 189, 248, 0.2);
            transition: transform 0.4s ease;
        }
        .hero img:hover {
            transform: scale(1.01);
        }

        .section-title {
            text-align: center;
            margin-bottom: 2.5rem;
            font-weight: 700;
            font-size: 2.4rem;
            background: linear-gradient(135deg, #FFFFFF, #BAE6FD);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* Service cards */
        .service-grid {
            display: grid;
            gap: 1.8rem;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
        }
        .service-card {
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(6px);
            border: 1px solid rgba(56, 189, 248, 0.15);
            border-radius: 28px;
            padding: 2rem 1.5rem;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        .service-card::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 50% 0%, rgba(56, 189, 248, 0.05), transparent 70%);
            opacity: 0;
            transition: opacity 0.4s;
        }
        .service-card:hover::after {
            opacity: 1;
        }
        .service-card:hover {
            transform: translateY(-10px);
            border-color: #38BDF8;
            box-shadow: 0 20px 40px -12px rgba(56, 189, 248, 0.25);
            background: rgba(22, 34, 56, 0.98);
        }
        .service-icon-wrapper {
            width: 90px;
            height: 90px;
            margin: 0 auto 1.2rem auto;
            background: rgba(56, 189, 248, 0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(56, 189, 248, 0.2);
            position: relative;
            z-index: 2;
        }
        .service-card:hover .service-icon-wrapper {
            background: linear-gradient(135deg, #38BDF8, #818CF8);
            transform: scale(1.08) rotate(-4deg);
            border-color: transparent;
        }
        .service-icon-wrapper i {
            font-size: 2.8rem;
            color: #38BDF8;
            transition: all 0.3s;
        }
        .service-card:hover .service-icon-wrapper i {
            color: #0B1120;
            text-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .service-card h3 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.7rem;
            color: #F1F5F9;
            position: relative;
            z-index: 2;
        }
        .service-card p {
            color: #94A3B8;
            font-size: 0.95rem;
            margin-bottom: 1.4rem;
            position: relative;
            z-index: 2;
        }

        /* Testimonials, News, Contact */
        .testimonial-grid, .news-grid, .contact-grid {
            display: grid;
            gap: 1.8rem;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }
        .testimonial-card, .news-card, .contact-card {
            background: #0F172A;
            border-radius: 28px;
            padding: 1.8rem;
            border: 1px solid #1E293B;
            transition: all 0.3s;
            box-shadow: 0 8px 20px -8px rgba(0,0,0,0.3);
        }
        .testimonial-card:hover, .news-card:hover, .contact-card:hover {
            border-color: #38BDF8;
            transform: translateY(-5px);
            background: #141E33;
            box-shadow: 0 12px 30px -8px rgba(56, 189, 248, 0.1);
        }
        .avatar {
            width: 54px;
            height: 54px;
            border-radius: 60px;
            background: linear-gradient(135deg, #38BDF8, #818CF8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.3rem;
            color: #0B1120;
            box-shadow: 0 6px 14px rgba(56, 189, 248, 0.2);
        }
        .stars {
            color: #FBBF24; /* keep gold for stars */
            margin-top: 12px;
            letter-spacing: 2px;
        }
        .news-image {
            height: 150px;
            background: linear-gradient(125deg, #1E293B, #0F172A);
            border-radius: 20px;
            margin-bottom: 1.2rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(56, 189, 248, 0.15);
        }
        .badge-date {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            color: #BAE6FD;
            padding: 4px 14px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        .faq-item {
            background: #0F172A;
            border-radius: 20px;
            margin-bottom: 1rem;
            padding: 1rem 1.5rem;
            border: 1px solid #1E293B;
            transition: 0.3s;
        }
        .faq-item:hover {
            border-color: rgba(56, 189, 248, 0.3);
        }
        .faq-question {
            font-weight: 700;
            cursor: pointer;
            color: #F1F5F9;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .faq-question i {
            color: #38BDF8;
            transition: transform 0.3s;
        }
        .faq-question[aria-expanded="true"] i {
            transform: rotate(180deg);
        }
        .faq-answer {
            padding-top: 0.8rem;
            color: #94A3B8;
            border-top: 1px solid #1E293B;
            margin-top: 0.7rem;
            font-size: 0.95rem;
        }

        .contact-card .service-icon-wrapper {
            width: 70px;
            height: 70px;
            margin-bottom: 1rem;
        }
        .contact-card .service-icon-wrapper i {
            font-size: 2.4rem;
        }
        .contact-card h4 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: white;
        }
        .contact-card a {
            color: #38BDF8;
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s;
        }
        .contact-card a:hover {
            color: white;
            text-decoration: underline;
        }

        footer {
            text-align: center;
            padding: 2rem;
            color: #64748B;
            border-top: 1px solid #1E293B;
            margin-top: 2rem;
            font-size: 0.85rem;
        }

        /* WhatsApp float removed – no floating button */

        a { color: #38BDF8; transition: 0.2s; }
        a:hover { color: #60C7FF; }
        .read-more {
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-top: 8px;
        }
        .text-primary { color: #38BDF8 !important; }

        @media (max-width: 768px) {
            .hero h1 { font-size: 2.4rem; }
            .glass-card { padding: 1.5rem; }
            .section-title { font-size: 1.8rem; }
            .service-icon-wrapper { width: 75px; height: 75px; }
            .service-icon-wrapper i { font-size: 2.2rem; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand logo" href="#">Mula Empire</a>
        <div class="ms-auto">
            <a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="btn btn-gradient">Join Now <i class="fas fa-rocket ms-1"></i></a>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="container">
        <div class="rating-badge"><i class="fas fa-star"></i> <i class="fas fa-star"></i> <i class="fas fa-star"></i> <i class="fas fa-star"></i> <i class="fas fa-star"></i> 5.0 · 94k+ happy earners</div>
        <h1>Your Time Is Money — Start Earning Instantly</h1>
        <p>Mula Empire empowers you to earn real cash by watching videos, answering trivia, chatting, and completing fun tasks — all from your phone or laptop.</p>
        <a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="btn btn-gradient btn-lg me-3">Create Free Account</a>
        <a href="https://chat.whatsapp.com/ILj3kFqrl6d1b1DNvSHUSt" class="btn btn-outline-light-custom btn-lg">Join Our Community</a>
        <div class="mt-5"><img src="https://online-gain.com/dashboard.jpg" alt="Mula Empire Dashboard" class="img-fluid rounded-4 shadow-lg" style="max-width: 100%;"></div>
    </div>
</section>

<!-- SERVICES -->
<div class="container my-5">
    <h2 class="section-title">Ways to Earn with Mula Empire</h2>
    <div class="service-grid">
        <div class="service-card"><div class="service-icon-wrapper"><i class="fab fa-tiktok"></i></div><h3>Watch TikTok & Earn</h3><p>Get paid for watching short videos. The more you watch, the more you earn — it’s that simple.</p><a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="btn-gradient btn-sm">Start Watching →</a></div>
        <div class="service-card"><div class="service-icon-wrapper"><i class="fab fa-youtube"></i></div><h3>YouTube Engagement</h3><p>Boost creators by watching YouTube content and earn rewards for every minute you spend.</p><a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="btn-gradient btn-sm">Start Earning →</a></div>
        <div class="service-card"><div class="service-icon-wrapper"><i class="fas fa-question-circle"></i></div><h3>Trivia Challenge</h3><p>Test your knowledge and win cash prizes. The smarter you answer, the higher your payout.</p><a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="btn-gradient btn-sm">Play & Earn →</a></div>
        <div class="service-card"><div class="service-icon-wrapper"><i class="fas fa-film"></i></div><h3>Movie Rewards</h3><p>Watch full-length movies and get paid for your time. Entertainment that pays back.</p><a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="btn-gradient btn-sm">Watch Now →</a></div>
        <div class="service-card"><div class="service-icon-wrapper"><i class="fab fa-whatsapp"></i></div><h3>WhatsApp Status Monetization</h3><p>Post promotional content on your status and earn per view — passive income at your fingertips.</p><a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="btn-gradient btn-sm">Start Posting →</a></div>
        <div class="service-card"><div class="service-icon-wrapper"><i class="fas fa-download"></i></div><h3>Download & Earn</h3><p>Download TikTok videos without watermarks and get paid for each download.</p><a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="btn-gradient btn-sm">Download Now →</a></div>
        <div class="service-card"><div class="service-icon-wrapper"><i class="fas fa-comments"></i></div><h3>Chat with the World</h3><p>Connect with people globally and earn money through meaningful conversations.</p><a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="btn-gradient btn-sm">Chat & Earn →</a></div>
        <div class="service-card"><div class="service-icon-wrapper"><i class="fas fa-headphones"></i></div><h3>Music Streaming Pays</h3><p>Listen to your favourite tracks online and earn while you enjoy the rhythm.</p><a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="btn-gradient btn-sm">Stream & Earn →</a></div>
    </div>
</div>

<!-- FEATURED -->
<div class="container">
    <div class="glass-card text-center">
        <h3>🌍 Available in Every Corner of Africa & Beyond</h3>
        <p class="mb-0">From Uganda, Kenya, Tanzania, Rwanda, Burundi to Nigeria, Ghana, Cameroon, Malawi, Zambia, Botswana, South Sudan, Congo, South Africa — and many more. Join the movement!</p>
    </div>
    <div class="glass-card text-center">
        <h3>📱 Download the Mula Empire App</h3>
        <p>Get the Android app directly from our website and start earning on the go.</p>
        <a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="btn-gradient">Download App</a>
    </div>
    <div class="glass-card text-center">
        <h3>💡 Pro Tip from Our Founder</h3>
        <p class="mb-0">Consistency is your superpower. Show up daily, complete tasks, and watch your earnings grow exponentially.</p>
    </div>
</div>

<!-- TESTIMONIALS -->
<div class="container my-5">
    <h2 class="section-title">Real Stories from Real Earners</h2>
    <p class="text-center text-white-50 mb-4">Thousands trust Mula Empire — here’s why</p>
    <div class="testimonial-grid">
        <div class="testimonial-card"><div class="testimonial-header d-flex gap-3 align-items-center mb-3"><div class="avatar">H</div><div><strong>Henry Musaasizi</strong><br><small class="text-secondary">Uganda · Fitness Coach</small></div></div><p>“Mali Wave turned my idle hours into a steady income. Withdrawals are lightning-fast!”</p><div class="stars">★★★★★</div></div>
        <div class="testimonial-card"><div class="testimonial-header d-flex gap-3 align-items-center mb-3"><div class="avatar">T</div><div><strong>Theresa Akinrujomu</strong><br><small class="text-secondary">Nigeria · Entrepreneur</small></div></div><p>“The easiest platform I’ve ever used. Tasks are fun, and the payouts are 100% real.”</p><div class="stars">★★★★★</div></div>
        <div class="testimonial-card"><div class="testimonial-header d-flex gap-3 align-items-center mb-3"><div class="avatar">M</div><div><strong>Mercy Gaceri</strong><br><small class="text-secondary">Kenya · Student</small></div></div><p>“I pay my tuition with Mula Empire earnings. Watching videos and answering trivia actually pays off!”</p><div class="stars">★★★★★</div></div>
    </div>
</div>

<!-- NEWS -->
<div class="container my-5">
    <h2 class="section-title">Insights & Updates</h2>
    <p class="text-center text-white-50 mb-4">Stay ahead with tips and news from the Mula Empire ecosystem</p>
    <div class="news-grid">
        <div class="news-card"><div class="news-image"><span class="badge-date">Trending</span></div><div class="news-content"><h4 class="text-white">Why wait for payday?</h4><p>Mula Empire lets you earn daily instead of waiting until the end of the month. Start today!</p><a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="read-more text-primary fw-bold">Learn More →</a></div></div>
        <div class="news-card"><div class="news-image"><span class="badge-date">Pro Tip</span></div><div class="news-content"><h4 class="text-white">If you love easy money, don’t miss this</h4><p>Discover the simple tasks that are generating consistent income for thousands of users.</p><a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="read-more text-primary fw-bold">Read Now →</a></div></div>
        <div class="news-card"><div class="news-image"><span class="badge-date">Guide</span></div><div class="news-content"><h4 class="text-white">Still struggling financially?</h4><p>This guide reveals how small online actions can build a sustainable income stream for you.</p><a href="<?php echo htmlspecialchars($affiliate_link); ?>" class="read-more text-primary fw-bold">Explore →</a></div></div>
    </div>
</div>

<!-- FAQ -->
<div class="container my-5">
    <h2 class="section-title">Frequently Asked Questions</h2>
    <div class="faq-item"><div class="faq-question" data-bs-toggle="collapse" data-bs-target="#faq1"><i class="fas fa-chevron-down"></i> What exactly is Mula Empire?</div><div id="faq1" class="collapse"><div class="faq-answer">Mula Empire is a modern online earning platform that rewards you for watching videos, answering trivia, chatting, and completing simple tasks — all from your mobile or computer.</div></div></div>
    <div class="faq-item"><div class="faq-question" data-bs-toggle="collapse" data-bs-target="#faq2"><i class="fas fa-chevron-down"></i> How do I start earning?</div><div id="faq2" class="collapse"><div class="faq-answer">All you need is a smartphone or laptop, an email address, a phone number, and a small activation fee (varies by country). No prior experience required — just your time and effort.</div></div></div>
    <div class="faq-item"><div class="faq-question" data-bs-toggle="collapse" data-bs-target="#faq3"><i class="fas fa-chevron-down"></i> When can I withdraw my earnings?</div><div id="faq3" class="collapse"><div class="faq-answer">You can withdraw anytime once you reach the minimum withdrawal threshold. Payments are processed quickly to your preferred mobile money or bank account.</div></div></div>
    <div class="faq-item"><div class="faq-question" data-bs-toggle="collapse" data-bs-target="#faq4"><i class="fas fa-chevron-down"></i> Is there a joining fee?</div><div id="faq4" class="collapse"><div class="faq-answer">Yes, a small activation fee applies, which varies per country (KES, UGX, NGN, XAF, SSP, etc.). This fee unlocks all earning opportunities on the platform.</div></div></div>
    <div class="faq-item"><div class="faq-question" data-bs-toggle="collapse" data-bs-target="#faq5"><i class="fas fa-chevron-down"></i> What are the requirements?</div><div id="faq5" class="collapse"><div class="faq-answer">A stable internet connection, a smartphone or laptop, a mobile money or bank account for withdrawals, and a TikTok account (optional but recommended for higher earnings). No ID required.</div></div></div>
</div>

<!-- CONTACT -->
<div class="container my-5">
    <h2 class="section-title">Get in Touch</h2>
    <p class="text-center text-white-50 mb-4">We’re here for you — reach out anytime</p>
    <div class="contact-grid">
        <div class="contact-card"><div class="service-icon-wrapper mx-auto mb-3" style="width: 70px; height: 70px;"><i class="fas fa-envelope fs-2"></i></div><h4>Email Support</h4><p>We respond within 24 hours</p><a href="mailto:<?php echo htmlspecialchars($email); ?>"><?php echo htmlspecialchars($email); ?></a></div>
        <div class="contact-card"><div class="service-icon-wrapper mx-auto mb-3" style="width: 70px; height: 70px;"><i class="fab fa-whatsapp fs-2"></i></div><h4>WhatsApp Community</h4><p>Join our active group for instant help</p><a href="https://chat.whatsapp.com/ILj3kFqrl6d1b1DNvSHUSt" target="_blank">Join Group</a></div>
        <div class="contact-card"><div class="service-icon-wrapper mx-auto mb-3" style="width: 70px; height: 70px;"><i class="fas fa-globe fs-2"></i></div><h4>Global Reach</h4><p>Supporting users across Africa and the world</p><span class="text-primary fw-bold">24/7 Support</span></div>
    </div>
</div>
<?php include 'chat-btn.php'; ?>
<footer>
    <p>Contact: <?php echo htmlspecialchars($email); ?> · WhatsApp: <?php echo htmlspecialchars($whatsapp_number); ?></p>
    <p>Follow us on Facebook, X (Twitter), LinkedIn, YouTube</p>
    <p>© <?php echo date('Y'); ?> Mula Empire — Empowering Financial Freedom</p>
</footer>
  <script src="https://cdn.ably.io/lib/ably.min-1.js"></script>
  <script src="assets/js/chat-btn.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>