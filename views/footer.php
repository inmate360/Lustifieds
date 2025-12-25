  </main>

  <footer class="mt-16 border-t border-gh-border bg-gh-panel">
    <div class="mx-auto max-w-7xl px-4 py-12">
      
      <div class="grid gap-8 sm:grid-cols-2 md:grid-cols-4">
        
        <div>
          <h4 class="mb-4 text-sm font-bold uppercase tracking-wider text-gh-fg">About</h4>
          <ul class="space-y-2.5 text-sm">
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="about.php">
                About us
              </a>
            </li>
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="how-it-works.php">
                How it works
              </a>
            </li>
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="safety.php">
                Safety tips
              </a>
            </li>
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="blog.php">
                Blog
              </a>
            </li>
          </ul>
        </div>

        <div>
          <h4 class="mb-4 text-sm font-bold uppercase tracking-wider text-gh-fg">Support</h4>
          <ul class="space-y-2.5 text-sm">
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="help.php">
                Help center
              </a>
            </li>
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="contact.php">
                Contact us
              </a>
            </li>
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="report.php">
                Report abuse
              </a>
            </li>
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="faq.php">
                FAQ
              </a>
            </li>
          </ul>
        </div>

        <div>
          <h4 class="mb-4 text-sm font-bold uppercase tracking-wider text-gh-fg">Legal</h4>
          <ul class="space-y-2.5 text-sm">
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="terms.php">
                Terms of service
              </a>
            </li>
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="privacy.php">
                Privacy policy
              </a>
            </li>
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="cookies.php">
                Cookie policy
              </a>
            </li>
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="dmca.php">
                DMCA
              </a>
            </li>
          </ul>
        </div>

        <div>
          <h4 class="mb-4 text-sm font-bold uppercase tracking-wider text-gh-fg">Community</h4>
          <ul class="space-y-2.5 text-sm">
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="forum.php">
                <i class="bi bi-chat-square-text-fill mr-1.5"></i>Forum
              </a>
            </li>
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="guidelines.php">
                Guidelines
              </a>
            </li>
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="membership.php">
                <i class="bi bi-gem mr-1.5"></i>Premium membership
              </a>
            </li>
            <li>
              <a class="text-gh-muted transition-colors hover:text-gh-fg hover:underline" href="bitcoin-wallet.php">
                <i class="bi bi-currency-bitcoin mr-1.5"></i>Bitcoin wallet
              </a>
            </li>
          </ul>
        </div>
      </div>

      <div class="mt-12 border-t border-gh-border pt-8">
        <div class="flex flex-col items-center justify-between gap-4 text-center text-sm text-gh-muted md:flex-row md:text-left">
          <div>
            <p>&copy; <?php echo date('Y'); ?> Turnpage. All rights reserved.</p>
          </div>
          
          <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2">
            <a class="transition-colors hover:text-gh-fg hover:underline" href="sitemap.php">
              Sitemap
            </a>
            <span class="text-gh-border">•</span>
            <a class="transition-colors hover:text-gh-fg hover:underline" href="choose-location.php">
              Browse locations
            </a>
            <span class="text-gh-border">•</span>
            <a class="transition-colors hover:text-gh-fg hover:underline" href="api-docs.php">
              API docs
            </a>
          </div>
        </div>

        <div class="mt-6 text-center">
          <div class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2 text-xs">
            <i class="bi bi-shield-check text-gh-success"></i>
            <span class="text-gh-muted">Safe & secure platform</span>
          </div>
        </div>
      </div>
    </div>
  </footer>

  <script>
    // Mobile menu toggle
    const mobileBtn = document.getElementById('mobileBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    if(mobileBtn && mobileMenu) {
      mobileBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
      });
    }

    // User dropdown
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenu = document.getElementById('userMenu');
    if(userMenuBtn && userMenu) {
      userMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = userMenu.dataset.open === 'true';
        userMenu.dataset.open = isOpen ? 'false' : 'true';
      });
      
      document.addEventListener('click', (e) => {
        if(!userMenu.contains(e.target)) {
          userMenu.dataset.open = 'false';
        }
      });
    }

    // Enable location helper
    function enableLocationFromBanner() {
      if(!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
      }
      
      navigator.geolocation.getCurrentPosition(
        (position) => {
          updateLocationSilent(position.coords.latitude, position.coords.longitude, () => {
            location.reload();
          });
        },
        (error) => {
          console.error('Geolocation error:', error);
          alert('Unable to get your location. Please enable location access.');
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
      );
    }

    function updateLocationSilent(latitude, longitude, callback) {
      const formData = new FormData();
      formData.append('action', 'update_location');
      formData.append('latitude', latitude);
      formData.append('longitude', longitude);
      formData.append('autodetected', 'true');
      
      fetch('api/location.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if(data.success && callback) callback();
      })
      .catch(error => console.error('Error updating location:', error));
    }

    // Theme toggle (if you add a button for it)
    function toggleTheme() {
      const html = document.documentElement;
      const currentTheme = html.classList.contains('dark') ? 'dark' : 'light';
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      
      html.classList.toggle('dark');
      html.dataset.theme = newTheme;
      
      // Save preference
      fetch('api/update-theme.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: newTheme })
      });
    }
  </script>
</body>
</html>
