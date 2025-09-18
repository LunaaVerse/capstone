<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QC traffic - Traffic & Transport Management System</title>
    <link rel="icon" type="image/png" href="img/traffic-logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- MapTiler for traffic mapping -->
    <link href="https://api.maptiler.com/maps/streets/style.json?key=gZtMDh9pV46hFgly6xCT" rel="stylesheet" />
    <script src="https://api.maptiler.com/maps/streets/?key=gZtMDh9pV46hFgly6xCT"></script>
    <script src="https://unpkg.com/maplibre-gl@2.4.0/dist/maplibre-gl.js"></script>
    <link href="https://unpkg.com/maplibre-gl@2.4.0/dist/maplibre-gl.css" rel="stylesheet" />
    <style>
        :root {
            --primary-blue: #1565C0;
            --dark-blue: #0D47A1;
            --light-blue: #42A5F5;
            --accent: #FF9800;
            --dark-gray: #212121;
            --light-gray: #f5f5f5;
            --text: #333333;
            --white: #ffffff;
            --gradient: linear-gradient(135deg, var(--dark-blue) 0%, var(--primary-blue) 100%);
            --gradient-dark: linear-gradient(135deg, var(--dark-blue) 0%, #002171 100%);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
            --hover-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            --glow: 0 0 15px rgba(21, 101, 192, 0.5);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            color: var(--text);
            background-color: var(--light-gray);
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        h1, h2, h3, h4, h5 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: var(--dark-blue);
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Particle Background */
        .particles-container {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
            overflow: hidden;
        }
        
        .particle {
            position: absolute;
            background-color: rgba(66, 165, 245, 0.3);
            border-radius: 50%;
            animation: float 15s infinite linear;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0) translateX(0) rotate(0deg);
                opacity: 0.3;
            }
            50% {
                opacity: 0.6;
            }
            100% {
                transform: translateY(-100vh) translateX(100px) rotate(360deg);
                opacity: 0;
            }
        }
        
        /* Loading Screen */
        #loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: black;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out;
        }
        
        .traffic-loader {
            position: relative;
            width: 100px;
            height: 120px;
            margin-bottom: 20px;
        }
        
        .traffic-light {
            position: absolute;
            width: 60px;
            height: 120px;
            background: #333;
            border-radius: 10px;
            left: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            align-items: center;
            padding: 10px 0;
        }
        
        .light {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #444;
        }
        
        .light.red {
            animation: red-light 3s infinite;
        }
        
        .light.yellow {
            animation: yellow-light 3s infinite 1s;
        }
        
        .light.green {
            animation: green-light 3s infinite 2s;
        }
        
        @keyframes red-light {
            0%, 33% { background: #ff3333; box-shadow: 0 0 15px #ff3333; }
            34%, 100% { background: #444; box-shadow: none; }
        }
        
        @keyframes yellow-light {
            0%, 33% { background: #ffcc00; box-shadow: 0 0 15px #ffcc00; }
            34%, 100% { background: #444; box-shadow: none; }
        }
        
        @keyframes green-light {
            0%, 33% { background: #00cc66; box-shadow: 0 0 15px #00cc66; }
            34%, 100% { background: #444; box-shadow: none; }
        }
        
        .loading-text {
            color: white;
            font-size: 1.2rem;
            letter-spacing: 2px;
            position: relative;
        }
        
        .loading-text:after {
            content: '';
            animation: loading-dots 1.5s infinite;
        }
        
        @keyframes loading-dots {
            0% { content: '.'; }
            33% { content: '..'; }
            66% { content: '...'; }
            100% { content: '.'; }
        }
        
        /* Header */
        header {
            background: var(--gradient);
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            min-height: 48px;
        }

        header.scrolled {
            padding: 0.8rem 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            background: var(--gradient-dark);
            min-height: 40px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo-img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3px;
            box-shadow: var(--glow);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .logo-img:hover {
            transform: rotate(15deg) scale(1.08);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
        }

        .logo-img img {
            width: 100%;
            height: auto;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .logo-text:after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: white;
            transition: width 0.3s ease;
        }

        .logo:hover .logo-text:after {
            width: 100%;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 0.7rem;
            align-items: center;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s;
            position: relative;
            padding: 0.3rem 0;
        }

        nav a:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: white;
            transition: width 0.3s;
        }

        nav a:hover:after {
            width: 100%;
        }

        .auth-buttons {
            display: flex;
            gap: 0.7rem;
            margin-left: 0.7rem;
        }

        .auth-btn {
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.85rem;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .auth-btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0%;
            height: 100%;
            background: white;
            transition: all 0.3s;
            z-index: -1;
        }

        .login-btn {
            background-color: transparent;
            color: white;
            border: 2px solid white;
        }

        .login-btn:hover:before {
            width: 100%;
        }

        .login-btn:hover {
            color: var(--primary-blue);
        }

        .register-btn {
            background-color: white;
            color: var(--primary-blue);
            border: 2px solid white;
        }

        .register-btn:before {
            background: var(--dark-blue);
        }

        .register-btn:hover:before {
            width: 100%;
        }

        .register-btn:hover {
            color: white;
            border-color: var(--dark-blue);
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            z-index: 1000;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }

        .mobile-menu-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        /* Language Selector */
        .language-selector {
            position: relative;
            margin-left: 0.7rem;
        }

        .language-btn {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 0.3rem 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .language-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .language-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            padding: 0.5rem 0;
            margin-top: 0.5rem;
            min-width: 120px;
            display: none;
            z-index: 1000;
        }

        .language-dropdown.active {
            display: block;
        }

        .language-option {
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--text);
            text-decoration: none;
            display: block;
            font-size: 0.95rem;
        }

        .language-option:hover {
            background: var(--light-gray);
            color: var(--primary-blue);
        }
        
        /* Hero Section */
        .hero {
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="0 0 800 400"><rect fill="%231565C0" width="800" height="400"/><path fill="%230D47A1" fill-opacity="0.4" d="M0 192L26.7 202.7C53.3 213 107 235 160 234.7C213.3 235 267 213 320 197.3C373.3 181 427 171 480 186.7C533.3 203 587 245 640 250.7C693.3 256 747 224 773.3 208L800 192L800 401L773.3 401C746.7 401 693 401 640 401C586.7 401 533 401 480 401C426.7 401 373 401 320 401C266.7 401 213 401 160 401C106.7 401 53 401 27 401L0 401Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            color: white;
            padding: 5rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        .hero h1 {
            font-size: 3.2rem;
            margin-bottom: 1.5rem;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            animation: fadeInUp 1s ease-out;
        }
        
        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2.5rem;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
            animation: fadeInUp 1.2s ease-out;
        }
        
        .btn {
            display: inline-block;
            background-color: white;
            color: var(--primary-blue);
            padding: 1rem 2rem;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            animation: fadeInUp 1.4s ease-out;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: 0.5s;
            z-index: -1;
        }
        
        .btn:hover:before {
            left: 100%;
        }
        
        .btn:hover {
            background-color: transparent;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }
        
        /* Widget Section */
        .widget-section {
            padding: 3rem 0;
            background-color: var(--light-gray);
        }
        
        .widget-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .widget-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s;
        }
        
        .widget-card:hover {
            transform: translateY(-5px);
        }
        
        .widget-card h3 {
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
        }
        
        .widget-card h3:after {
            content: '';
            position: absolute;
            width: 50px;
            height: 3px;
            bottom: -8px;
            left: 0;
            background: var(--primary-blue);
        }
        
        .location-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .location-form input {
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .location-form button {
            background: var(--gradient);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .location-form button:hover {
            background: var(--gradient-dark);
        }
        
        .nearest-station {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: var(--light-gray);
            border-radius: 8px;
            display: none;
        }
        
        .nearest-station.active {
            display: block;
        }
        
        .nearest-station h4 {
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
        }
        
        /* Services Section */
        .services {
            padding: 5rem 0;
            background-color: white;
            position: relative;
        }
        
        .services:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100" opacity="0.03"><path d="M0 0 L100 100 M100 0 L0 100" stroke="%231565C0" stroke-width="2"/></svg>');
            background-size: 200px;
            pointer-events: none;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 3.5rem;
            position: relative;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            width: 80px;
            height: 4px;
            background-color: var(--primary-blue);
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2.5rem;
        }
        
        .service-card {
            background: var(--light-gray);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: var(--card-shadow);
            position: relative;
            top: 0;
            border: 1px solid rgba(21, 101, 192, 0.1);
        }
        
        .service-card:hover {
            transform: translateY(-12px);
            box-shadow: var(--hover-shadow);
        }
        
        .service-icon {
            background: var(--gradient);
            color: white;
            font-size: 2.2rem;
            padding: 2rem;
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .service-icon::after {
            content: '';
            position: absolute;
            width: 40px;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transform: skewX(-20deg) translateX(-200%);
            transition: transform 0.6s;
        }
        
        .service-card:hover .service-icon::after {
            transform: skewX(-20deg) translateX(500%);
        }
        
        .service-content {
            padding: 2rem;
        }
        
        .service-content h3 {
            margin-bottom: 1.2rem;
            font-size: 1.4rem;
            position: relative;
            display: inline-block;
        }
        
        .service-content h3:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background: var(--primary-blue);
            transition: width 0.3s;
        }
        
        .service-card:hover .service-content h3:after {
            width: 100%;
        }
        
        .service-content p {
            color: #555;
        }
        
        /* About Us Section */
        .about {
            padding: 5rem 0;
            background-color: var(--light-gray);
            position: relative;
        }
        
        .about:before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><polygon points="50,0 100,50 50,100 0,50" fill="%231565C0" opacity="0.05"/></svg>');
            background-size: contain;
            background-repeat: no-repeat;
            pointer-events: none;
        }
        
        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }
        
        .about-text h2 {
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
        }
        
        .about-text h2:after {
            content: '';
            position: absolute;
            width: 100%;
            height: 3px;
            bottom: -10px;
            left: 0;
            background: var(--gradient);
            border-radius: 3px;
        }
        
        .about-text p {
            margin-bottom: 1.5rem;
            line-height: 1.8;
        }
        
        .about-image {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--hover-shadow);
            transition: transform 0.5s;
            position: relative;
            border: 5px solid white;
        }
        
        .about-image:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient);
            opacity: 0;
            z-index: 1;
            transition: opacity 0.3s;
            border-radius: 8px;
        }
        
        .about-image:hover:before {
            opacity: 0.1;
        }
        
        .about-image:hover {
            transform: scale(1.02);
        }
        
        .about-image img {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.5s;
        }
        
        .about-image:hover img {
            transform: scale(1.05);
        }
        
        /* Leadership Section */
        .leadership {
            padding: 5rem 0;
            background-color: white;
            position: relative;
        }
        
        .leadership:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(to right, transparent, var(--primary-blue), transparent);
        }
        
        .leadership-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2.5rem;
        }
        
        .leader-card {
            background: var(--light-gray);
            border-radius: 12px;
            overflow: hidden;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s;
            position: relative;
            border: 1px solid rgba(21, 101, 192, 0.1);
        }
        
        .leader-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--hover-shadow);
        }
        
        .leader-image {
            width: 100%;
            height: 250px;
            background-color: #f0f0f0;
            position: relative;
            overflow: hidden;
        }
        
        .leader-image:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient);
            opacity: 0;
            z-index: 1;
            transition: opacity 0.3s;
            border-radius: 8px;
        }
        
        .leader-card:hover .leader-image:before {
            opacity: 0.2;
        }
        
        .leader-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .leader-card:hover .leader-image img {
            transform: scale(1.1);
        }
        
        .leader-info {
            padding: 1.5rem;
        }
        
        .leader-info h3 {
            margin-bottom: 0.5rem;
            position: relative;
            display: inline-block;
        }
        
        .leader-info h3:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background: var(--primary-blue);
            transition: width 0.3s;
            border-radius: 2px;
        }
        
        .leader-card:hover .leader-info h3:after {
            width: 100%;
        }
        
        .leader-role {
            color: var(--primary-blue);
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .leader-info p {
            color: 555;
            font-size: 0.9rem;
        }
        
        /* Stats Section */
        .stats {
            padding: 5rem 0;
            background: var(--gradient);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .stats::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100" opacity="0.05"><circle cx="50" cy="50" r="40" fill="white" /></svg>');
            background-size: 200px;
            opacity: 0.1;
        }
        
        .stats h2 {
            color: white;
            text-align: center;
            margin-bottom: 3.5rem;
            position: relative;
            z-index: 1;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2.5rem;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .stat-item {
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(5px);
            transition: transform 0.3s;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            z-index: -1;
        }
        
        .stat-item:hover {
            transform: scale(1.05);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .stat-item h3 {
            font-size: 2.8rem;
            margin-bottom: 0.8rem;
            color: white;
            text-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }
        
        .stat-item p {
            font-size: 1.1rem;
        }
        
        /* Chart Section */
        .chart-section {
            padding: 5rem 0;
            background-color: var(--light-gray);
            position: relative;
        }
        
        .chart-section:before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(to right, transparent, var(--primary-blue), transparent);
        }
        
        .chart-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2.5rem;
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(21, 101, 192, 0.1);
        }
        
        .chart-container:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--gradient);
        }
        
        /* Map Section */
        .map-section {
            padding: 80px 0;
            background: var(--white);
        }
        
        .map-container {
            width: 100%;
            height: 500px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 40px;
            position: relative;
        }
        
        #map {
            width: 100%;
            height: 100%;
        }
        
        .map-controls {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 10;
            display: flex;
            gap: 10px;
        }
        
        .map-btn {
            background: var(--white);
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            font-weight: 500;
            box-shadow: var(--card-shadow);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .map-btn:hover {
            background: var(--primary-blue);
            color: var(--white);
        }
        
        .maplibregl-popup-content {
            background: var(--white);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 15px;
            font-family: 'Roboto', sans-serif;
        }
        
        .maplibregl-popup-content h4 {
            color: var(--primary-blue);
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .maplibregl-popup-content p {
            margin: 0;
            font-size: 14px;
            color: var(--text);
        }
        
        /* Map Fullscreen */
        .map-fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            border-radius: 0;
        }
        
        /* Traffic Management Center Section */
        .station-section {
            padding: 80px 0;
            background: var(--light-gray);
        }
        
        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .search-filter {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-input, .district-filter {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus, .district-filter:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.1);
        }
        
        .station-table {
            background: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }
        
        .station-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .station-table th {
            background: var(--gradient);
            color: var(--white);
            padding: 20px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .station-table td {
            padding: 18px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .station-table tbody tr:hover {
            background: #fafafa;
            transition: background 0.3s ease;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .status-maintenance {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            background: var(--white);
            color: var(--text);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .pagination-btn:hover, .pagination-btn.active {
            background: var(--primary-blue);
            color: var(--white);
            border-color: var(--primary-blue);
        }
        
        /* Footer */
        footer {
            background-color: var(--dark-gray);
            color: white;
            padding: 4rem 0 2rem;
            position: relative;
        }
        
        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--gradient);
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
        }
        
        .footer-logo {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .footer-logo img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
        }
        
        .footer-logo-text {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .footer-about p {
            margin-bottom: 1.5rem;
            line-height: 1.8;
            color: #ccc;
        }
        
        .footer-heading {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
        }
        
        .footer-heading:after {
            content: '';
            position: absolute;
            width: 40px;
            height: 3px;
            bottom: -8px;
            left: 0;
            background: var(--primary-blue);
            border-radius: 3px;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 1rem;
        }
        
        .footer-links a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        .footer-links i {
            color: var(--primary-blue);
            font-size: 1.2rem;
        }
        
        .footer-contact {
            list-style: none;
        }
        
        .footer-contact li {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .footer-contact i {
            color: var(--primary-blue);
            font-size: 1.2rem;
            margin-top: 5px;
        }
        
        .footer-contact p {
            color: #ccc;
            line-height: 1.6;
        }
        
        .footer-bottom {
            margin-top: 4rem;
            padding-top: 2rem;
            border-top: 1px solid #444;
            text-align: center;
            color: #999;
        }
        
        .social-links {
            display: flex;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background-color: #333;
            color: white;
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 1.2rem;
        }
        
        .social-links a:hover {
            background-color: var(--primary-blue);
            transform: translateY(-3px);
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .about-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .about-image {
                order: -1;
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-wrap: wrap;
            }
            
            nav {
                order: 3;
                width: 100%;
                margin-top: 1rem;
                display: none;
            }
            
            nav.active {
                display: block;
            }
            
            nav ul {
                flex-direction: column;
                gap: 0;
                align-items: flex-start;
            }
            
            nav li {
                width: 100%;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }
            
            nav a {
                display: block;
                padding: 1rem 0;
            }
            
            .auth-buttons {
                margin-left: auto;
                margin-right: 1rem;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .language-selector {
                margin-left: auto;
            }
            
            .hero {
                padding: 3rem 0;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .btn {
                padding: 0.8rem 1.5rem;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }
        
        @media (max-width: 576px) {
            .logo-text {
                font-size: 1.2rem;
            }
            
            .auth-btn {
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
            }
            
            .hero h1 {
                font-size: 1.8rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .service-card, .leader-card {
                max-width: 320px;
                margin: 0 auto;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-logo {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
            
            .footer-logo-text {
                font-size: 1.3rem;
            }
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        
        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--gradient);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: var(--card-shadow);
            transition: all 0.3s;
            z-index: 99;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
        }
        
        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);

            .status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-active {
    background: #e8f5e8;
    color: #2e7d32;
}

.status-maintenance {
    background: #fff3e0;
    color: #f57c00;
}
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div id="loading-screen">
        <div class="traffic-loader">
            <div class="traffic-light">
                <div class="light red"></div>
                <div class="light yellow"></div>
                <div class="light green"></div>
            </div>
        </div>
        <div class="loading-text">QCtraffic is loading</div>
    </div>
    
    <!-- Particle Background -->
    <div class="particles-container" id="particles-container"></div>
    
    <!-- Header -->
    <header id="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <div class="logo-img">
                        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="50" cy="50" r="45" fill="#1565C0" />
                            <rect x="30" y="30" width="40" height="40" fill="white" />
                            <circle cx="40" cy="40" r="5" fill="#1565C0" />
                            <circle cx="60" cy="40" r="5" fill="#1565C0" />
                            <circle cx="40" cy="60" r="5" fill="#1565C0" />
                            <circle cx="60" cy="60" r="5" fill="#1565C0" />
                        </svg>
                    </div>
                    <div class="logo-text">QCtraffic</div>
                </div>
                
                <nav id="nav">
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#traffic-map">Traffic Map</a></li>
                        <li><a href="#stations">Traffic Centers</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </nav>
                
                <div class="auth-buttons">
                    <a href="login.php" class="auth-btn login-btn">Login</a>
                    <a href="register.php" class="auth-btn register-btn">Register</a>
                </div>
                
                <div class="language-selector">
                    <button class="language-btn">
                        <i class="fas fa-globe"></i> EN
                    </button>
                    <div class="language-dropdown">
                        <a href="#" class="language-option">English</a>
                        <a href="#" class="language-option">Filipino</a>
                    </div>
                </div>
                
                <button class="mobile-menu-btn" id="mobile-menu-btn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>
    
    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <h1>Quezon City Traffic & Transport Management</h1>
                <p>Real-time traffic monitoring, route planning, and transportation services for a smoother commute in Quezon City</p>
                <a href="#traffic-map" class="btn">View Live Traffic Map</a>
            </div>
        </div>
    </section>
    
    <!-- Widget Section -->
    <section class="widget-section">
        <div class="container">
            <div class="widget-grid">
                <div class="widget-card">
                    <h3>Traffic Conditions</h3>
                    <div class="traffic-status">
                        <div class="status-item">
                            <span class="status-indicator green"></span>
                            <span>Light Traffic: 65%</span>
                        </div>
                        <div class="status-item">
                            <span class="status-indicator yellow"></span>
                            <span>Moderate Traffic: 25%</span>
                        </div>
                        <div class="status-item">
                            <span class="status-indicator red"></span>
                            <span>Heavy Traffic: 10%</span>
                        </div>
                    </div>
                </div>
                
               <div class="widget-card">
    <h3>Find Nearest Traffic Center</h3>
    <form class="location-form" id="location-form">
        <input type="text" placeholder="Enter your location (e.g. East Ave)" id="location-input" required>
        <button type="submit" id="find-station-btn">Find Nearest Center</button>
    </form>
    <div class="nearest-station" id="nearest-station">
        <!-- Results will be displayed here -->
    </div>
</div>
                
                <div class="widget-card">
                    <h3>Public Transport Status</h3>
                    <div class="transport-status">
                        <div class="transport-item">
                            <i class="fas fa-bus"></i>
                            <span>Buses: Normal Operation</span>
                        </div>
                        <div class="transport-item">
                            <i class="fas fa-train"></i>
                            <span>MRT: Normal Operation</span>
                        </div>
                        <div class="transport-item">
                            <i class="fas fa-taxi"></i>
                            <span>Taxis/Jeepeys: Normal Operation</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <h2 class="section-title">Our Services</h2>
            <div class="services-grid">
                <div class="service-card fade-in">
                    <div class="service-icon">
                        <i class="fas fa-traffic-light"></i>
                    </div>
                    <div class="service-content">
                        <h3>Traffic Management</h3>
                        <p>Real-time traffic monitoring and management to ensure smooth flow of vehicles across Quezon City.</p>
                    </div>
                </div>
                
                <div class="service-card fade-in">
                    <div class="service-icon">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <div class="service-content">
                        <h3>Route Planning</h3>
                        <p>Find the fastest routes with real-time traffic updates and alternative path suggestions.</p>
                    </div>
                </div>
                
                <div class="service-card fade-in">
                    <div class="service-icon">
                        <i class="fas fa-car-crash"></i>
                    </div>
                    <div class="service-content">
                        <h3>Accident Response</h3>
                        <p>Quick response to traffic incidents and accidents to minimize disruption and assist those involved.</p>
                    </div>
                </div>
                
                <div class="service-card fade-in">
                    <div class="service-icon">
                        <i class="fas fa-bus"></i>
                    </div>
                    <div class="service-content">
                        <h3>Public Transport Coordination</h3>
                        <p>Coordination of public transportation systems for efficient and reliable service.</p>
                    </div>
                </div>
                
                <div class="service-card fade-in">
                    <div class="service-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="service-content">
                        <h3>Road Work Management</h3>
                        <p>Planning and management of road works to minimize impact on traffic flow.</p>
                    </div>
                </div>
                
                <div class="service-card fade-in">
                    <div class="service-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="service-content">
                        <h3>Mobile Alerts</h3>
                        <p>Receive real-time traffic alerts and notifications on your mobile device.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Map Section -->
    <section class="map-section" id="traffic-map">
        <div class="container">
            <h2 class="section-title">Live Traffic Map</h2>
            <p style="text-align: center; margin-bottom: 30px;">Real-time traffic conditions and incident reports across Quezon City</p>
            
            <div class="map-container">
                <div id="map"></div>
                <div class="map-controls">
                    <button class="map-btn" id="zoom-in-btn"><i class="fas fa-plus"></i></button>
                    <button class="map-btn" id="zoom-out-btn"><i class="fas fa-minus"></i></button>
                    <button class="map-btn" id="fullscreen-btn"><i class="fas fa-expand"></i></button>
                    <button class="map-btn" id="locate-btn"><i class="fas fa-location-arrow"></i></button>
                </div>
            </div>
            
            <div class="map-legend">
                <div class="legend-item">
                    <div class="color-box" style="background-color: #4CAF50;"></div>
                    <span>#</span>
                </div>
                <div class="legend-item">
                    <div class="color-box" style="background-color: #FFC107;"></div>
                    <span>#</span>
                </div>
                <div class="legend-item">
                    <div class="color-box" style="background-color: #F44336;"></div>
                    <span>#</span>
                </div>
                <div class="legend-item">
                    <div class="color-box" style="background-color: #9C27B0;"></div>
                    <span>#</span>
                </div>
                <div class="legend-item">
                    <div class="color-box" style="background-color: #607D8B;"></div>
                    <span>#</span>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Traffic Management Center Section -->
    <section class="station-section" id="stations">
        <div class="container">
            <h2 class="section-title">Traffic Management Centers</h2>
            <p style="text-align: center; margin-bottom: 40px;">Our network of traffic management centers across Quezon City</p>
            
            <div class="table-controls">
                <div class="search-filter">
                    <input type="text" class="search-input" placeholder="Search centers..." id="search-input">
                    <select class="district-filter" id="district-filter">
                        <option value="">All Districts</option>
                        <option value="1">District 1</option>
                        <option value="2">District 2</option>
                        <option value="3">District 3</option>
                        <option value="4">District 4</option>
                        <option value="5">District 5</option>
                        <option value="6">District 6</option>
                    </select>
                </div>
            </div>
            
            <div class="station-table">
                <table>
                    <thead>
                        <tr>
                            <th>Center Name</th>
                            <th>District</th>
                            <th>Address</th>
                            <th>Contact</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>QC Traffic District 1</td>
                            <td>District 1</td>
                            <td>Balintawak, Quezon City</td>
                            <td>(02) 8921 4567</td>
                            <td><span class="status-badge status-active">Active</span></td>
                        </tr>
                        <tr>
                            <td>QC Traffic District 2</td>
                            <td>District 2</td>
                            <td>Project 4, Quezon City</td>
                            <td>(02) 8922 5678</td>
                            <td><span class="status-badge status-active">Active</span></td>
                        </tr>
                        <tr>
                            <td>QC Traffic District 3</td>
                            <td>District 3</td>
                            <td>East Ave, Diliman, Quezon City</td>
                            <td>(02) 8923 6789</td>
                            <td><span class="status-badge status-maintenance">Maintenance</span></td>
                        </tr>
                        <tr>
                            <td>QC Traffic District 4</td>
                            <td>District 4</td>
                            <td>Cubao, Quezon City</td>
                            <td>(02) 8924 7890</td>
                            <td><span class="status-badge status-active">Active</span></td>
                        </tr>
                        <tr>
                            <td>QC Traffic District 5</td>
                            <td>District 5</td>
                            <td>Project 8, Quezon City</td>
                            <td>(02) 8925 8901</td>
                            <td><span class="status-badge status-active">Active</span></td>
                        </tr>
                        <tr>
                            <td>QC Traffic District 6</td>
                            <td>District 6</td>
                            <td>Commonwealth, Quezon City</td>
                            <td>(02) 8926 9012</td>
                            <td><span class="status-badge status-active">Active</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <button class="pagination-btn active">1</button>
                <button class="pagination-btn">2</button>
                <button class="pagination-btn">3</button>
                <button class="pagination-btn">></button>
            </div>
        </div>
    </section>
    
    <!-- About Us Section -->
    <section class="about" id="about">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2>About QCtrafficHub</h2>
                    <p>QCtrafficHub is Quezon City's comprehensive traffic and transport management system, designed to improve urban mobility and reduce congestion throughout the city.</p>
                    <p>Our system integrates real-time traffic monitoring, intelligent traffic signal control, public transport coordination, and incident response management into a single platform.</p>
                    <p>With advanced technologies and a dedicated team of traffic management professionals, we work to ensure safer and more efficient transportation for all residents and visitors of Quezon City.</p>
                </div>
                <div class="about-image">
                    <svg viewBox="0 0 600 400" xmlns="http://www.w3.org/2000/svg">
                        <rect width="600" height="400" fill="#f5f5f5" />
                        <path d="M0,200 Q150,100 300,200 T600,200" stroke="#1565C0" stroke-width="3" fill="none" />
                        <circle cx="100" cy="180" r="8" fill="#1565C0" />
                        <circle cx="200" cy="160" r="8" fill="#1565C0" />
                        <circle cx="300" cy="200" r="8" fill="#1565C0" />
                        <circle cx="400" cy="220" r="8" fill="#1565C0" />
                        <circle cx="500" cy="190" r="8" fill="#1565C0" />
                        <rect x="80" y="175" width="15" height="10" fill="#FF9800" />
                        <rect x="180" y="155" width="15" height="10" fill="#FF9800" />
                        <rect x="280" y="195" width="15" height="10" fill="#FF9800" />
                        <rect x="380" y="215" width="15" height="10" fill="#FF9800" />
                        <rect x="480" y="185" width="15" height="10" fill="#FF9800" />
                    </svg>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Leadership Section -->
    <section class="leadership">
        <div class="container">
            <h2 class="section-title">Traffic Management Leadership</h2>
            <div class="leadership-grid">
                <div class="leader-card fade-in">
                    <div class="leader-image">
                        <svg viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="150" cy="150" r="150" fill="#e0e0e0" />
                            <circle cx="150" cy="120" r="60" fill="#cccccc" />
                            <rect x="90" y="180" width="120" height="100" fill="#cccccc" />
                        </svg>
                    </div>
                    <div class="leader-info">
                        <h3>Atty. Jesus Manuel C. Sison</h3>
                        <div class="leader-role">Director, Traffic Management Bureau</div>
                        <p>Oversees all traffic management operations and strategic planning for Quezon City.</p>
                    </div>
                </div>
                
                <div class="leader-card fade-in">
                    <div class="leader-image">
                        <svg viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="150" cy="150" r="150" fill="#e0e0e0" />
                            <circle cx="150" cy="120" r="60" fill="#cccccc" />
                            <rect x="90" y="180" width="120" height="100" fill="#cccccc" />
                        </svg>
                    </div>
                    <div class="leader-info">
                        <h3>Engr. Maria Lourdes R. Santos</h3>
                        <div class="leader-role">Head, Traffic Engineering Division</div>
                        <p>Leads the design and implementation of traffic control systems and infrastructure.</p>
                    </div>
                </div>
                
                <div class="leader-card fade-in">
                    <div class="leader-image">
                        <svg viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="150" cy="150" r="150" fill="#e0e0e0" />
                            <circle cx="150" cy="120" r="60" fill="#cccccc" />
                            <rect x="90" y="180" width="120" height="100" fill="#cccccc" />
                        </svg>
                    </div>
                    <div class="leader-info">
                        <h3>Col. Ricardo G. Flores</h3>
                        <div class="leader-role">Chief, Traffic Enforcement Unit</div>
                        <p>Manages traffic law enforcement and officer deployment across the city.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <h2>Traffic Management Impact</h2>
            <div class="stats-grid">
                <div class="stat-item fade-in">
                    <h3>32%</h3>
                    <p>Reduction in average travel time</p>
                </div>
                
                <div class="stat-item fade-in">
                    <h3>45%</h3>
                    <p>Decrease in traffic accidents</p>
                </div>
                
                <div class="stat-item fade-in">
                    <h3>78%</h3>
                    <p>Public satisfaction rate</p>
                </div>
                
                <div class="stat-item fade-in">
                    <h3>500+</h3>
                    <p>Traffic sensors deployed</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Chart Section -->
    <section class="chart-section">
        <div class="container">
            <h2 class="section-title">Traffic Volume Trends</h2>
            <div class="chart-container">
                <canvas id="trafficChart"></canvas>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer id="contact">
        <div class="container">
            <div class="footer-content">
                <div class="footer-about">
                    <div class="footer-logo">
                        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="50" cy="50" r="45" fill="#1565C0" />
                            <rect x="30" y="30" width="40" height="40" fill="white" />
                            <circle cx="40" cy="40" r="5" fill="#1565C0" />
                            <circle cx="60" cy="40" r="5" fill="#1565C0" />
                            <circle cx="40" cy="60" r="5" fill="#1565C0" />
                            <circle cx="60" cy="60" r="5" fill="#1565C0" />
                        </svg>
                        <div class="footer-logo-text">QCtrafficHub</div>
                    </div>
                    <p>Quezon City's integrated traffic and transport management system dedicated to improving urban mobility and reducing congestion.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h3 class="footer-heading">Quick Links</h3>
                    <ul>
                        <li><a href="#home"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="#services"><i class="fas fa-chevron-right"></i> Services</a></li>
                        <li><a href="#traffic-map"><i class="fas fa-chevron-right"></i> Traffic Map</a></li>
                        <li><a href="#stations"><i class="fas fa-chevron-right"></i> Traffic Centers</a></li>
                        <li><a href="#about"><i class="fas fa-chevron-right"></i> About Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h3 class="footer-heading">Services</h3>
                    <ul>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Traffic Management</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Route Planning</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Accident Response</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Public Transport</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Road Work Management</a></li>
                    </ul>
                </div>
                
                <div class="footer-contact">
                    <h3 class="footer-heading">Contact Us</h3>
                    <ul>
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <p>Quezon City Hall, Elliptical Road, Diliman, Quezon City</p>
                        </li>
                        <li>
                            <i class="fas fa-phone"></i>
                            <p>(02) 8988 4242</p>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <p>info@qctraffichub.gov.ph</p>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <p>24/7 Operations Center</p>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2023 QCtrafficHub - Quezon City Traffic & Transport Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Back to Top Button -->
    <a href="#" class="back-to-top" id="back-to-top">
        <i class="fas fa-arrow-up"></i>
    </a>
    
    <script>
        // Loading Screen
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('loading-screen').style.opacity = '0';
                setTimeout(function() {
                    document.getElementById('loading-screen').style.display = 'none';
                }, 500);
            }, 2000);
        });
        
        // Create particles for background
        function createParticles() {
            const container = document.getElementById('particles-container');
            const particleCount = 30;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                // Random size
                const size = Math.random() * 20 + 5;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                // Random position
                const posX = Math.random() * 100;
                const posY = Math.random() * 100;
                particle.style.left = `${posX}%`;
                particle.style.top = `${posY}%`;
                
                // Random animation delay
                const delay = Math.random() * 15;
                particle.style.animationDelay = `${delay}s`;
                
                container.appendChild(particle);
            }
        }
        
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            const backToTop = document.getElementById('back-to-top');
            
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
            
            if (window.scrollY > 500) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });
        
        // Mobile menu toggle
        document.getElementById('mobile-menu-btn').addEventListener('click', function() {
            const nav = document.getElementById('nav');
            nav.classList.toggle('active');
            
            const icon = this.querySelector('i');
            if (nav.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
        
        // Language selector toggle
        const languageBtn = document.querySelector('.language-btn');
        const languageDropdown = document.querySelector('.language-dropdown');
        
        languageBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            languageDropdown.classList.toggle('active');
        });
        
        document.addEventListener('click', function(e) {
            if (!languageBtn.contains(e.target) && !languageDropdown.contains(e.target)) {
                languageDropdown.classList.remove('active');
            }
        });
        
        // Find nearest station
        document.getElementById('find-station-btn').addEventListener('click', function() {
            const locationInput = document.getElementById('location-input').value;
            if (locationInput.trim() !== '') {
                const nearestStation = document.getElementById('nearest-station');
                nearestStation.classList.add('active');
                
                // Simulate loading
                nearestStation.innerHTML = '<p>Finding nearest traffic center...</p>';
                
                setTimeout(function() {
                    nearestStation.innerHTML = `
                        <h4>Quezon City Traffic District 3</h4>
                        <p>Distance: 1.2 km</p>
                        <p>Address: East Ave, Diliman, Quezon City</p>
                        <p>Contact: (02) 8929 5093</p>
                    `;
                }, 1500);
            }
        });
        
        // Scroll animations
        function checkScroll() {
            const elements = document.querySelectorAll('.fade-in');
            
            elements.forEach(function(element) {
                const position = element.getBoundingClientRect().top;
                const screenPosition = window.innerHeight / 1.3;
                
                if (position < screenPosition) {
                    element.classList.add('visible');
                }
            });
        }
        
        window.addEventListener('scroll', checkScroll);
        window.addEventListener('load', checkScroll);
        
        // Back to top button
        document.getElementById('back-to-top').addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    // Close mobile menu if open
                    const nav = document.getElementById('nav');
                    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
                    const icon = mobileMenuBtn.querySelector('i');
                    
                    if (nav.classList.contains('active')) {
                        nav.classList.remove('active');
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                    
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Initialize Map
        function initMap() {
            // Default coordinates for Quezon City
            const qcCoordinates = [121.050, 14.650];
            
            // Initialize the map
            const map = new maplibregl.Map({
                container: 'map',
                style: `https://api.maptiler.com/maps/streets/style.json?key=gZtMDh9pV46hFgly6xCT`,
                center: qcCoordinates,
                zoom: 11,
                pitch: 45,
                bearing: 0
            });
            
            // Add navigation control
            map.addControl(new maplibregl.NavigationControl());
            
            // Add traffic incidents and congestion data (simulated)
            map.on('load', function() {
                // Simulated traffic data points
                const trafficData = [
                    { coordinates: [121.025, 14.657], type: 'heavy', description: 'Heavy traffic on EDSA northbound' },
                    { coordinates: [121.032, 14.642], type: 'moderate', description: 'Moderate traffic on Quezon Ave' },
                    { coordinates: [121.061, 14.633], type: 'accident', description: 'Accident on Commonwealth Ave' },
                    { coordinates: [121.049, 14.676], type: 'roadwork', description: 'Road work on Aurora Blvd' },
                    { coordinates: [121.017, 14.668], type: 'light', description: 'Light traffic on Mindanao Ave' }
                ];
                
                // Add markers for each traffic incident
                trafficData.forEach(function(incident) {
                    // Create a marker
                    const marker = new maplibregl.Marker({
                        color: getColorForType(incident.type),
                        scale: 0.8
                    })
                    .setLngLat(incident.coordinates)
                    .setPopup(new maplibregl.Popup({ offset: 25 })
                    .setHTML(`<h4>${getTitleForType(incident.type)}</h4><p>${incident.description}</p>`))
                    .addTo(map);
                });
            });
            
            // Map control functions
            document.getElementById('zoom-in-btn').addEventListener('click', function() {
                map.zoomIn();
            });
            
            document.getElementById('zoom-out-btn').addEventListener('click', function() {
                map.zoomOut();
            });
            
            document.getElementById('fullscreen-btn').addEventListener('click', function() {
                const mapContainer = document.querySelector('.map-container');
                mapContainer.classList.toggle('map-fullscreen');
                
                const icon = this.querySelector('i');
                if (mapContainer.classList.contains('map-fullscreen')) {
                    icon.classList.remove('fa-expand');
                    icon.classList.add('fa-compress');
                } else {
                    icon.classList.remove('fa-compress');
                    icon.classList.add('fa-expand');
                }
                
                map.resize();
            });
            
            document.getElementById('locate-btn').addEventListener('click', function() {
                // Use browser geolocation if available
                if ('geolocation' in navigator) {
                    navigator.geolocation.getCurrentPosition(function(position) {
                        map.flyTo({
                            center: [position.coords.longitude, position.coords.latitude],
                            zoom: 14
                        });
                    });
                } else {
                    alert('Geolocation is not supported by your browser');
                }
            });
            
            // Helper functions for traffic types
            function getColorForType(type) {
                switch(type) {
                    case 'heavy': return '#F44336';
                    case 'moderate': return '#FFC107';
                    case 'light': return '#4CAF50';
                    case 'accident': return '#9C27B0';
                    case 'roadwork': return '#607D8B';
                    default: return '#1565C0';
                }
            }
            
            function getTitleForType(type) {
                switch(type) {
                    case 'heavy': return 'Heavy Traffic';
                    case 'moderate': return 'Moderate Traffic';
                    case 'light': return 'Light Traffic';
                    case 'accident': return 'Traffic Accident';
                    case 'roadwork': return 'Road Work';
                    default: return 'Traffic Incident';
                }
            }
        }
        
        // Initialize Traffic Chart
        function initTrafficChart() {
            const ctx = document.getElementById('trafficChart').getContext('2d');
            
            // Sample traffic data
            const trafficChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Average Traffic Volume',
                        data: [65, 59, 80, 81, 56, 55, 72, 68, 75, 80, 85, 90],
                        backgroundColor: 'rgba(21, 101, 192, 0.2)',
                        borderColor: 'rgba(21, 101, 192, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: 'rgba(21, 101, 192, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Monthly Traffic Volume Trends in Quezon City (2023)',
                            font: {
                                size: 16,
                                family: "'Montserrat', sans-serif"
                            }
                        },
                        legend: {
                            position: 'bottom',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Traffic Volume Index'
                            }
                        }
                    }
                }
            });
        }
        
        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            initMap();
            initTrafficChart();
        });
    </script>
</body>
</html>