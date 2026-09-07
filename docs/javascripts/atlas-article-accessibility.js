(() => {
  const enhanceSkipNavigation = () => {
    const articleStart = document.querySelector('[data-atlas-article-start]');
    const skip = document.querySelector('[data-md-component="skip"] .md-skip');

    if (articleStart === null || skip === null || !articleStart.id) {
      return;
    }

    skip.href = `#${articleStart.id}`;
    if (skip.dataset.atlasSkipNavigationEnhanced === 'true') {
      return;
    }

    skip.dataset.atlasSkipNavigationEnhanced = 'true';
    skip.addEventListener('click', (event) => {
      const articleStart = document.querySelector('[data-atlas-article-start]');
      if (articleStart === null || !articleStart.id) {
        return;
      }

      event.preventDefault();
      window.location.hash = articleStart.id;
      articleStart.focus();
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceSkipNavigation);
  } else {
    enhanceSkipNavigation();
  }

  if (typeof document$ !== 'undefined') {
    document$.subscribe(enhanceSkipNavigation);
  }
})();
