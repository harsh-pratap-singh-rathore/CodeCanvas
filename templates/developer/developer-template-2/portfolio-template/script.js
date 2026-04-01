/**
 * Developer Portfolio Template - Core Logic
 */

// 1. Send Message Function
function sendMessage() {
  const name = document.getElementById('name').value;
  const email = document.getElementById('email').value;
  const message = document.getElementById('message').value;

  // Basic validation
  if (!name || !email || !message) {
    alert("Please fill in all fields before sending.");
    return;
  }

  // Simple email format validation
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(email)) {
    alert("Please enter a valid email address.");
    return;
  }

  // Logging data (in a real app, you'd send this to an API)
  console.log("Message Received:", { name, email, message });

  // Show success alert
  alert("Message Sent Successfully!");

  // Clear form
  document.getElementById('name').value = '';
  document.getElementById('email').value = '';
  document.getElementById('message').value = '';
}

// 2. Scroll Reveal Animation
function reveal() {
  const reveals = document.querySelectorAll('.reveal');

  reveals.forEach(element => {
    const windowHeight = window.innerHeight;
    const elementTop = element.getBoundingClientRect().top;
    const elementVisible = 150;

    if (elementTop < windowHeight - elementVisible) {
      element.classList.add('active');
    }
  });
}

// 3. Smooth Navigation for Anchors
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    e.preventDefault();
    const targetId = this.getAttribute('href');
    if (targetId === '#') return;
    
    const targetElement = document.querySelector(targetId);
    if (targetElement) {
      window.scrollTo({
        top: targetElement.offsetTop - 70, // Offset for potential header
        behavior: 'smooth'
      });
    }
  });
});

// Initialize reveal on load and scroll
window.addEventListener('scroll', reveal);
window.addEventListener('DOMContentLoaded', () => {
  // Initial check for elements in view
  reveal();
  
  // Stagger initial hero reveal slightly
  setTimeout(() => {
    const hero = document.getElementById('home');
    if (hero) hero.classList.add('active');
  }, 100);
});

// 4. Parallax effect for bg-shapes (Subtle)
window.addEventListener('mousemove', (e) => {
  const shapes = document.querySelectorAll('.shape');
  const x = e.clientX / window.innerWidth;
  const y = e.clientY / window.innerHeight;
  
  shapes.forEach((shape, index) => {
    const speed = (index + 1) * 20;
    const moveX = (x * speed);
    const moveY = (y * speed);
    shape.style.transform = `translate(${moveX}px, ${moveY}px)`;
  });
});
