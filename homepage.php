<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ArenaX</title>
    
        <!-- Favicon link using local image for testing -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <link rel="icon" href="img/logo3.png" type="image/png">
        <head>
    <style>
        
        @import url('https://fonts.googleapis.com/css2?family=Aboreto&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400&family=Goldman&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Bodoni+Moda:wght@400;700&display=swap');


        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cinzel Decorative', cursive;
            overflow-x: hidden;
            color: #333;
        }
    

        .hero {
    min-height: 90vh; /* Changed from 100vh to 70vh */
    width: 100%;
    position: relative;
    overflow: hidden;
    display: flex;
    justify-content: center;
    align-items: center;
}

.hero .slide {
    min-width: 100%;
    height: 100vh; /* Changed from 100vh to 70vh */
    background-size: cover;
    background-position: center;
}

        .hero .slider {
            display: flex;
            width: calc(100% * 9); /* Original 7 slides + 2 duplicates */
            transition: transform 0.5s ease-in-out;
        }

      

        .hero .slide:nth-child(1) { background-image: url('img/image4.png'); } /* Duplicate of last */
        .hero .slide:nth-child(2) { background-image: url('img/image6.png'); }
        .hero .slide:nth-child(3) { background-image: url('img/image2.png'); }
        .hero .slide:nth-child(4) { background-image: url('img/image3.png'); }
        .hero .slide:nth-child(5) { background-image: url('img/image5.png'); }
        .hero .slide:nth-child(6) { background-image: url('img/image7.png'); }
        .hero .slide:nth-child(7) { background-image: url('img/image1.png'); }
        .hero .slide:nth-child(8) { background-image: url('img/image4.png'); }
        .hero .slide:nth-child(9) { background-image: url('img/image6.png'); } /* Duplicate of first */

        .hero h1 {
            position: absolute;
            font-size: 6rem;
            color: white;
            font-weight: bold;
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.7);
            text-align: center;
            font-family: 'Cinzel Decorative', cursive;
            z-index: 10;
        }

        .hero-info {
            position: absolute;
            top: 50%;
            left: 10%;
            transform: translateY(-50%);
            text-align: left;
            color: white;
        }
        
        .hero-info p {
            margin: 5px 0;
            font-size: 60px;
            line-height: 52px;
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.7);
            color: white;
            font-family: 'Bodoni Moda', serif;
            font-style: italic;
        }
        
        .icons {
            display: flex;
            flex-direction: row;
            gap: 20px;
            margin-top: 15px;
        }

        .icon {
            position: relative;
            width: 200px; /* Increased width */
            height: 60px;
            font-size: 23px;
            background: rgba(255, 255, 255, 0.597);
            border-radius: 8px;
            display: flex;
            align-items: center;
            padding-left: 20px; /* Space for the icon on the left */
            cursor: pointer;
            transition: all 0.3s ease-in-out;
        }
        
        .icon:hover {
            background: white;
            color: black;
            transform: scale(1.05);
        }
        
        .icon i {
            font-size: 24px;
            color: rgb(10, 20, 85);
            margin-right: 15px; /* Space between the icon and the text */
            transition: color 0.3s;
        }
        
        .icon:hover i {
            color: black;
        }
        
        .icon-label {
            font-size: 20px;
            color: rgb(10, 1, 1);
            transition: color 0.3s ease-in-out;
        }
        
        .icon:hover .icon-label {
            color: black;
        }
        
        .head{
            text-decoration: none;
        }
        
        .are{
            text-decoration: none;
        }
        .scroll-indicator {
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    width: 50px;
    height: 50px;
    border-radius: 60%;
    cursor: pointer;
    z-index: 10;
    animation: bounce 2s infinite;
}

.scroll-indicator::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: 24px;
    height: 24px;
    border-left: 3px solid white;
    border-bottom: 3px solid white;
    transform: translateX(-50%) rotate(-45deg);
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateX(-50%) translateY(0);
    }
    40% {
        transform: translateX(-50%) translateY(-10px);
    }
    60% {
        transform: translateX(-50%) translateY(-5px);
    }
}
        /* Arrow styles */
        .arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 3rem;
            color: white;
            background: rgba(0, 0, 0, 0);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            z-index: 10;
            transition: background 0.7s ease-in-out;
        }

        .arrow:hover {
            background: rgba(180, 244, 248, 0.433);
        }

        .arrow.left {
            left: 20px;
        }

        .arrow.right {
            right: 20px;
        }
        

        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.8);
            padding: 2rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
           transform: translateY(-100%);
            transition: transform 0.4s ease-in-out;
            z-index: 1000;
        }
        #about {
            scroll-margin-top: 100px; /* height of your header */
        }
        .header.scrolled {
            transform: translateY(0);
        }
        

        .header nav ul {
    list-style: none;
    display: flex;
    gap: 2rem;
    justify-content: center;
    margin: 0 auto;
    padding: 0;
}

.header div a button {
    transition: background-color 0.3s ease-in-out, transform 0.2s ease;
}

.header div a button:hover {
    color: #00bcd4;
    border-color: #00bcd4;
    transform: scale(1.1);
}
.log
{
    padding: 10px 20px;
    font-size: 1rem; 
    font-family: 'Cinzel Decorative', cursive; 
    background-color: #007cd400; 
    color: white;
    border-style: solid;
    border-width:1px;
    border-color: white;
    border-radius: 0px; 
    cursor: pointer;
}

.header div {
    display: flex;
    gap: 15px;
    margin-right: 40px; /* Adjust spacing to push buttons further right */
}


       .header nav ul li {
            position: relative;
        }

        .header nav ul li a {
            text-decoration: none;
            color: white;
            font-size: 1.2rem;
            transition: color 0.2s ease-in-out;
        }

        .header nav ul li a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            width: 0;
            height: 2px;
            background: #00bcd4;
            transition: all 0.3s ease-in-out;
            transform: translateX(-50%);
        }

        .header nav ul li a:hover::after {
            width: 100%;
        }

        .header nav ul li a:hover {
            color: #00bcd4;
        }

        .content {
            padding: 6rem 3rem;
            text-align: left;
            font-family: 'Newsreader', serif;
            background-image: linear-gradient(rgba(235, 224, 205, 0.21), rgba(74, 62, 62, 0.23)), url(img/foot.png);
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .content-box {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 3rem;
            border-radius: 0 500px 500px 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            max-width: 1500px;
            margin: 0;
            position: relative;
            overflow: hidden;
            display: flex;
            gap: 80px;
            align-items: center;
            opacity: 0;
            transform: translateY(50px);
            transition: opacity 1.5s ease, transform 1.5s ease;
        }

        .content-box.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .content-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(to bottom, #72aab0, #eead88);
        }

        .content-text {
            flex: 1;
            padding-right: 20px;
        }

        .content-image {
            flex: 1;
            max-width: 500px;
            height: 400px;
            overflow: hidden;
            border-radius: 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            margin-right: 100px;
        }

        .content-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .content-image:hover img {
            /* Removed the transform: scale(1.05) property */
        }

        .content h2 {
            margin-bottom: 2rem;
            font-size: 3.5rem;
            color: #333;
            position: relative;
            display: inline-block;
        }

        .content h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 100px;
            height: 3px;
            background: linear-gradient(to right, #72aab0, #eead88);
        }

        .content p {
            color: #555;
            font-size: 1.4rem;
            line-height: 1.8;
            margin: 0;
            position: relative;
            padding: 20px 0;
        }

        .about-features {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
        }

        .feature {
            flex: 1;
            padding: 20px;
            text-align: center;
            max-width: 250px;
        }

        .feature i {
            font-size: 2.5rem;
            color: #72aab0;
            margin-bottom: 15px;
        }

        .feature h3 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 10px;
        }

        .feature p {
            font-size: 1rem;
            color: #666;
        }

        @media (max-width: 968px) {
            .content-box {
                flex-direction: column;
                padding: 2rem;
            }

            .content-image {
                max-width: 100%;
                height: 300px;
                order: -1;
            }

            .content-text {
                padding-right: 0;
            }

            .content h2 {
                font-size: 2.5rem;
            }

            .content p {
                font-size: 1.2rem;
            }
        }

        .course {
            width: 80%;
            margin: auto;
            text-align: center;
            padding-top: 100px;
        }

        h1 {
            font-size: 36px;
            font-weight: 600;
        }

        p {
            color: #777;
            font-size: 14px;
            font-weight: 300;
            line-height: 22px;
            padding: 10px;
        }

    
        /* Membership Plans Section */
.facil {
    padding: 80px 20px;
    background-image: linear-gradient(rgba(246, 249, 252, 0.23), rgba(238, 244, 249, 0.15)), url('img/f3.png'); /* Added background image with overlay */
    background-size: cover;
    background-position: center;
    background-attachment: fixed; /* Creates parallax effect */
    text-align: center;
    font-family: 'Newsreader', serif;
}

.facil h1 {
    font-size: 3rem;
    color: #2c3e50;
    margin-bottom: 50px;
    position: relative;
    display: inline-block;
    font-family: 'Newsreader', serif;
}

.facil h1::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 3px;
    background: linear-gradient(90deg, #72aab0, #eead88);
}

.row {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
    max-width: 1800px; /* Increased from 1400px */
    margin: 0 auto;
}

.fac-col {
    position: relative;
    width: 400px;
    min-height: 550px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: all 0.3s ease;
    opacity: 0;
    transform: translateY(50px);
    transition: transform 1.5s ease, opacity 1.5s ease, box-shadow 0.3s ease;
}

.fac-col.visible {
    opacity: 1;
    transform: translateY(0);
}

/* Stagger the animations */
.fac-col:nth-child(1) {
    transition-delay: 0s;
}

.fac-col:nth-child(2) {
    transition-delay: 0.3s;
}

.fac-col:nth-child(3) {
    transition-delay: 0.6s;
}

.fac-col:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
}

.fac-col img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    border-bottom: 4px solid;
}

/* Custom border colors for each plan */
.fac-col:nth-child(1) img { border-color: #C0C0C0; }
.fac-col:nth-child(2) img { border-color: #FFD700; }
.fac-col:nth-child(3) img { border-color: #E5E4E2; }

.layer {
    padding: 30px;
    display: flex;
    flex-direction: column;
    height: calc(100% - 200px);
}

.layer h3 {
    font-size: 2.2rem;
    margin-bottom: 20px;
    font-weight: 700;
    font-family: 'Newsreader', serif;
}

.layer ul {
    list-style: none;
    text-align: left;
    margin: 20px 0;
    flex-grow: 1;
}

.layer ul li {
    font-size: 1.1rem;
    margin: 15px 0;
    color: #555;
    display: flex;
    align-items: center;
    font-family: 'Newsreader', serif;
}

.layer ul li::before {
    content: '✓';
    color: #72aab0;
    margin-right: 10px;
    font-weight: bold;
}

/* Custom colors for each plan */
.fac-col:nth-child(1) .layer h3 { color: #808080; }
.fac-col:nth-child(2) .layer h3 { color: #DAA520; }
.fac-col:nth-child(3) .layer h3 { color: #666666; }

.see-more-btn {
    display: inline-block;
    padding: 15px 30px;
    background: linear-gradient(135deg, #72aab0 0%, #eead88 100%);
    color: white;
    text-decoration: none;
    font-size: 1.1rem;
    font-weight: 600;
    border-radius: 30px;
    transition: all 0.3s ease;
    border: none;
    margin-top: auto;
    font-family: 'Newsreader', serif;
}

.see-more-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    background: linear-gradient(135deg, #eead88 0%, #72aab0 100%);
}

/* Price tags - Add these if you want to display prices */
.price-tag {
    font-size: 2rem;
    font-weight: 700;
    margin: 10px 0 20px;
    color: #2c3e50;
    font-family: 'Newsreader', serif;
}

.price-tag span {
    font-size: 1rem;
    color: #666;
}

/* Responsive Design */
@media (max-width: 768px) {
    .facil h1 {
        font-size: 2.5rem;
    }

    .row {
        gap: 20px;
    }

    .fac-col {
        width: 90%;
        max-width: 450px; /* Increased from 350px */
    }
}

.camp-container {
    display: flex;
    width: 100%; /* Full width of the parent */
}

.camp-left,
.camp-right {
    flex: 1; /* Equal width for both sections */
    padding: 20px;
    height:auto; /* Adjusts height based on content */
}



.camp-left {
    
    background-color: #72aab0c0; /* Light orange background */
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
    text-align: left;
    font-family: 'Goldman', cursive; /* Apply Goldman font */
}
.camp-left p {
    margin: 0;
    font-size: 100px; /* Increase font size to 100px */
    font-weight: bold;
    color: #f7f2f2;
    line-height: 1.2; /* Adjust line-height for better spacing */
}

.camp-right {
    background-color: #eead88; /* Light blue background */
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
    font-family: 'Goldman', cursive;
}

.camp-right p {
    font-size: 2rem;
    color: #f4eded;
    line-height: 1.8;
    margin: 0;
}
/* Footer */
/* Footer Styles */
footer {
    background-color: #282c34;
    font-family: 'Goldman', cursive;
    color: white;
    padding: 40px 20px;
}

.footer-container {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    max-width: 1200px;
    margin: 0 auto;
}

.footer-column {
    flex: 1;
    min-width: 250px;
    margin: 10px;
}

.footer-column h3 {
    margin-bottom: 15px;
    font-size: 18px;
    text-transform: uppercase;
    color: #9f799e; /* Highlighted color for headings */
}

.footer-column p,
.footer-column ul {
    font-size: 14px;
    line-height: 1.6;
}

.footer-column ul {
    list-style: none;
    padding: 0;
}

.footer-column ul li {
    margin-bottom: 10px;
}

.footer-column ul li a {
    color: white;
    text-decoration: none;
    transition: color 0.3s ease;
}

.footer-column ul li a:hover {
    color: #00eeff; /* Highlight color on hover */
}

.social-links {
    display: flex;
    gap: 10px;
}

.social-links a {
    color: white;
    font-size: 20px;
    transition: color 0.3s ease;
}

.social-links a:hover {
    color: #6ad3d8; /* Highlight color on hover */
}

.footer-bottom {
    text-align: center;
    padding-top: 20px;
    font-size: 14px;
    border-top: 1px solid #444;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .footer-container {
        flex-direction: column;
        text-align: center;
    }

    .footer-column {
        margin: 20px 0;
    }
}



            .abt-button {
    margin-top: 20px;
    padding: 12px 22px;  /* Increased padding by 2px from 10px 20px */
    font-size: 1.2rem;
    font-weight: bold;
    color: #fff;
    background-color: #bb904cc1;
    border: none;
    border-radius: 0;
    cursor: pointer;
    transition: background-color 0.3s ease-in-out;
    display: inline-block;
    text-decoration: none;
}

.abt-button:hover {
    background-color:rgba(136, 102, 48, 0.76);
}

/* New Camp Section Styles */
.camp {
    padding: 100px 0;
    background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/f3.png');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    color: white;
    position: relative;
}

.camp h1 {
    text-align: center;
    font-size: 3rem;
    color: white;
    font-family: 'Goldman', cursive;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    top: 30px;
}

.camp-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 80px 20px 0 20px; /* Increased top padding to create more space below heading */
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
}

.camp-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 40px 30px;
    text-align: center;
    opacity: 0;
    transform: translateX(-100px); /* Start from left */
    transition: transform 1.5s ease, opacity 1.5s ease, box-shadow 0.3s ease;
}

.camp-card.visible {
    opacity: 1;
    transform: translateX(0); /* Slide to original position */
}

/* Stagger the animations */
.camp-card:nth-child(1) {
    transition-delay: 0s;
}

.camp-card:nth-child(2) {
    transition-delay: 0.3s;
}

.camp-card:nth-child(3) {
    transition-delay: 0.6s;
}

.camp-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.camp-icon {
    font-size: 3rem;
    margin-bottom: 20px;
    color: #72aab0;
}

.camp-title {
    font-family: 'Goldman', cursive;
    font-size: 1.8rem;
    margin-bottom: 15px;
    color: #fff;
}

.camp-description {
    font-family: 'Newsreader', serif;
    font-size: 1.1rem;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 25px;
}

.camp-button {
    display: inline-block;
    padding: 12px 25px;
    background: linear-gradient(135deg, #72aab0 0%, #eead88 100%);
    color: white;
    text-decoration: none;
    border-radius: 25px;
    font-family: 'Newsreader', serif;
    font-size: 1rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.camp-button:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

@media (max-width: 1024px) {
    .camp-container {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .camp-container {
        grid-template-columns: 1fr;
    }
    
    .camp {
        padding: 60px 0;
    }
}

.camp h1::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, #72aab0, #eead88);
}

html {
    scroll-behavior: smooth;
}

#about {
    scroll-margin-top: 100px; /* height of your header */
}

#membership {
    scroll-margin-top: 100px; /* height of your header */
}

    </style>
</head>
<body>
    <div class="hero">
        <div class="slider">
            <!-- Duplicate of last slide -->
            <div class="slide"></div>
            <!-- Original slides -->
            <div class="slide"></div>
            <div class="slide"></div>
            <div class="slide"></div>
            <div class="slide"></div>
            <div class="slide"></div>
            <div class="slide"></div>
            <!-- Duplicate of first slide -->
            <div class="slide"></div>
            <div class="slide"></div>
        </div>
        
        <div class="arrow left" id="prev">&#10094;</div>
        <div class="arrow right" id="next">&#10095;</div>
        <div class="scroll-indicator" onclick="scrollToContent()"></div>
        <div class="hero-info">
            <p>EXPLORE INTO</p><p> THE WIDE</p>
            <p>VARIETY OF ACTIVITIES</p>
            <br><br>
            <div class="icons">
    <a href="indoor.php" style="text-decoration: none;">
        <div class="icon" data-hover="Outdoor">
            <i class="fa-solid fa-chess"></i>
            <span class="icon-label">Indoor</span>
        </div>
    </a>
    <a href="outdoor.php" style="text-decoration: none;">
        <div class="icon" data-hover="Outdoor">
            <i class="fa-solid fa-tree"></i>
            <span class="icon-label">Outdoor</span>
        </div>
    </a>
    <a href="fitness.php" style="text-decoration: none;">
        <div class="icon" data-hover="Fitness">
            <i class="fa-solid fa-dumbbell"></i>
            <span class="icon-label">Fitness</span>
        </div>
    </a>
</div>
            </div>
        </div>
    </div>

        
    
    <header class="header">
        <div class="logo">
          <a href="homepage.php" class="are"><h2 style="color: white;">ArenaX</h2></a>  
        </div>
        <nav>
            <ul style="display: flex; justify-content: center; width: 100%;">
                <li><a href="#about">About</a></li>
                <li><a href="indoor.php">Indoor</a></li>
                <li><a href="outdoor.php">Outdoor</a></li>
                <li><a href="fitness.php">Fitness</a></li>
                <li><a href="#membership">Membership</a></li>
            </ul>
        </nav>
        <div style="margin-right: 20px; display: flex; gap: 10px;">
            <a href="signup.php">
                <button class="log" >Sign Up</button>
            </a>
            <a href="signin.php">
                <button class="log">Sign In</button>
            </a>
        </div>
    </header>
    
    
    
        <div class="content" id="about">
            <div class="content-box">
                <div class="content-text">
                    <h2>About Us</h2>
                    <p>Discover the ultimate destination for indoor and outdoor games, fitness, and fun. From exciting games like chess and billiards to outdoor activities like horse riding and golf, we have it all under one roof. Our mission is to provide a comprehensive sports and recreation facility that caters to all ages and skill levels, making fitness and sports accessible to everyone in our community.</p>
                    <a href="signup.php" class="abt-button">Learn More</a>
                </div>
                <div class="content-image">
                    <img src="img/foot.png" alt="About Us">
                </div>
            </div>
        </div>
        
        <section class="facil" id="membership">
    <h1>Membership Plans</h1>
    <div class="row">
        <!-- Normal Membership -->
        <div class="fac-col">
            <img src="img/f1.png" alt="Normal Plan">
            <div class="layer">
                <h3>Normal</h3>
                <div class="price-tag">Free (₹0)</div>
                <ul>
                    <li>Basic access to indoor activities</li>
                    <li>Basic access to outdoor activities</li>
                    <li>Full access to fitness activities</li>
                    <li>Schedue Reminders</li>
                    <li>Limited bookings per month</li>



                </ul>
                <a href="signup.php" class="see-more-btn">Choose Plan</a>
            </div>
        </div>

        <!-- Standard Membership -->
        <div class="fac-col">
            <img src="img/f2.png" alt="Standard Plan">
            <div class="layer">
                <h3>Standard</h3>
                <div class="price-tag">₹1000<span>/month</span></div>
                <ul>
                    <li>All Free benefits included</li>
                    <li>Full access to indoor activities</li>
                    <li>Personal Gallary Access</li>
                    <li>Calender Access to view schedules</li>
                    <li>Limited access to events</li>
                </ul>


                <a href="signup.php" class="see-more-btn">Choose Plan</a>
            </div>
        </div>

        <!-- Premium Membership -->
        <div class="fac-col">
            <img src="img/f3.png" alt="Premium Plan">
            <div class="layer">
                <h3>Premium</h3>
                <div class="price-tag">₹2000<span>/month</span></div>
                <ul>
                    <li>All Standard benefits included</li>
                    <li>Full access to all facilities</li>
                    <li>Access to all events</li>
                    <li>Access to booking for guests</li>
                    <li>20% Discount on bookings</li>
                </ul>
                <a href="signup.php" class="see-more-btn">Choose Plan</a>
            </div>
        </div>
    </div>
</section>

    <section class="camp">
        <h1>OUR PROGRAMS</h1>
        <div class="camp-container">
            <div class="camp-card">
                <i class="fas fa-users camp-icon"></i>
                <h3 class="camp-title">Community Events</h3>
                <p class="camp-description">Join our vibrant community events and connect with fellow sports enthusiasts. Participate in tournaments, workshops, and social gatherings.</p>
                <a href="signup.php" class="camp-button">Join Now</a>
            </div>
            
            <div class="camp-card">
                <i class="fas fa-trophy camp-icon"></i>
                <h3 class="camp-title">Championships</h3>
                <p class="camp-description">Compete in our seasonal championships across various sports categories. Show your skills and win exciting prizes.</p>
                <a href="signup.php" class="camp-button">Register</a>
            </div>
            
            <div class="camp-card">
                <i class="fas fa-heart camp-icon"></i>
                <h3 class="camp-title">Wellness Programs</h3>
                <p class="camp-description">Experience our holistic wellness programs designed to improve your physical and mental well-being.</p>
                <a href="signup.php" class="camp-button">Learn More</a>
            </div>
        </div>
    </section>
    <footer>
    <div class="footer-container">
        <div class="footer-column">
            <h3>ArenaX</h3>
            <p>Your premier destination for sports and fitness. Explore a variety of activities and join our vibrant community.</p>
        </div>
        <div class="footer-column">
            <h3>Quick Links</h3>
            <ul>
                <li><a href="homepage.php">Home</a></li>
                <li><a href="#about">About Us</a></li>
                <li><a href="indoor.php">Indoor Activities</a></li>
                <li><a href="outdoor.php">Outdoor Activities</a></li>
                <li><a href="#membership">Membership</a></li>
            </ul>
        </div>
        <div class="footer-column">
            <h3>Contact Us</h3>
            <p>Email: arenax@gmail.com</p>
            <p>Phone: 9544147855</p>
            <p>Address: 123 ArenaX Avenue, Sportstown</p>
        </div>
        <div class="footer-column">
            <h3>Follow Us</h3>
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-linkedin"></i></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2025 ArenaX. All rights reserved.</p>
    </div>
</footer>

    <script>

        const header = document.querySelector('.header');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        const slider = document.querySelector('.slider');
        const slides = document.querySelectorAll('.slide');
        const prevArrow = document.getElementById('prev');
        const nextArrow = document.getElementById('next');

        let currentIndex = 1; // Start on the first actual slide
        const totalSlides = slides.length - 2; // Exclude duplicates

        // Function to update slider position
        function updateSlider(instant = false) {
            slider.style.transition = instant ? 'none' : 'transform 0.5s ease-in-out';
            slider.style.transform = `translateX(-${currentIndex * 100}%)`;
        }

        // Automatically change slides every 3 seconds
        let autoSlide = setInterval(() => {
            moveToNextSlide();
        }, 3000);

        // Function to move to next slide
        function moveToNextSlide() {
            currentIndex++;
            updateSlider();
            if (currentIndex > totalSlides) {
                setTimeout(() => {
                    currentIndex = 1; // Reset to the first actual slide
                    updateSlider(true);
                }, 500); // Delay to allow transition to complete
            }
        }

        // Function to move to previous slide
        function moveToPrevSlide() {
            currentIndex--;
            updateSlider();
            if (currentIndex < 1) {
                setTimeout(() => {
                    currentIndex = totalSlides; // Reset to the last actual slide
                    updateSlider(true);
                }, 500); // Delay to allow transition to complete
            }
        }

        // Previous arrow functionality
        prevArrow.addEventListener('click', () => {
            clearInterval(autoSlide);
            moveToPrevSlide();
            autoSlide = setInterval(() => {
                moveToNextSlide();
            }, 3000);
        });

        // Next arrow functionality
        nextArrow.addEventListener('click', () => {
            clearInterval(autoSlide);
            moveToNextSlide();
            autoSlide = setInterval(() => {
                moveToNextSlide();
            }, 3000);
        });

        // Initial position
        updateSlider();
        
        function scrollToContent() {
    const contentSection = document.querySelector('.content');
    contentSection.scrollIntoView({ behavior: 'smooth' });
}

    // Add this new code for About Us animation
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target); // Stop observing once animation is triggered
            }
        });
    }, {
        threshold: 0.2 // Trigger when 20% of the element is visible
    });

    // Observe the content box
    const contentBox = document.querySelector('.content-box');
    observer.observe(contentBox);

    // Add this new code for membership plans animation
    const planObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const plans = entry.target.querySelectorAll('.fac-col');
                plans.forEach(plan => {
                    plan.classList.add('visible');
                });
                planObserver.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.2
    });

    // Observe the membership plans container
    const plansContainer = document.querySelector('.row');
    planObserver.observe(plansContainer);

    // Add this new code for camp cards animation
    const campObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const cards = entry.target.querySelectorAll('.camp-card');
                cards.forEach(card => {
                    card.classList.add('visible');
                });
                campObserver.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.2
    });

    // Observe the camp container
    const campContainer = document.querySelector('.camp-container');
    campObserver.observe(campContainer);
    </script>
</body>
</html>
