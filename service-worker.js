self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open('bimo-cache-v1').then((cache) => {
      return cache.addAll([
        '/',
        '/create_post.html',
        '/index.html',
        '/signup.html',
        '/signin.html',
        '/Blog.html',
        '/Features.html',
        '/index.html',
        '/LoginPageBimo.html',
        '/News.html',
        '/Posts.html',
        '/SignUpBimo.html',
        '/user_profile.html',
        '/WaitList.html',
        '/waitlistcomplete.html',
        '/WelcomePageBimo.html',
        '/WelcomeToBimo.html',
        '/favicon3.png',
        '/Votality.html',
        '/market.html',
        '/favicon5.ico',
        '/Votality.ico',
        '/Votality.jpg',
        '/Votalityscreenshot.jpg',
        '/Votality.mp4',
        '/Votality2.mp4',
        '/Votality3.mp4',
        '/Votality4.mp4',
        '/Votality5.mp4',
        '/service-worker.js',
        '/manifest.json'
      ]);
    })
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      return response || fetch(event.request);
    })
  );
});