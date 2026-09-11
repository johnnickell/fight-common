(() => {
  const tabSets = '[data-atlas-format-tabs]';

  const tabsFor = (tabSet) => Array.from(tabSet.querySelectorAll('[role="tab"]'));

  const selectTab = (tabSet, selectedTab, focus = false) => {
    const tabs = tabsFor(tabSet);

    tabs.forEach((tab) => {
      const selected = tab === selectedTab;
      const panel = tabSet.querySelector(`#${tab.getAttribute('aria-controls')}`);

      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
      if (panel) {
        panel.hidden = !selected;
      }
    });

    if (focus) {
      selectedTab.focus();
    }
  };

  const copyText = async (text) => {
    if (navigator.clipboard) {
      await navigator.clipboard.writeText(text);
      return;
    }

    const fallback = document.createElement('textarea');
    fallback.value = text;
    fallback.setAttribute('readonly', '');
    fallback.style.position = 'fixed';
    fallback.style.opacity = '0';
    document.body.append(fallback);
    fallback.select();
    const copied = document.execCommand('copy');
    fallback.remove();
    if (!copied) {
      throw new Error('The browser did not allow copying this configuration.');
    }
  };

  const announceCopyStatus = (button, message) => {
    const status = button.closest(tabSets)?.querySelector('[data-atlas-copy-status]');

    if (status) {
      status.textContent = message;
    }
  };

  const configurationNameFor = (button) => (
    button.getAttribute('aria-label')?.replace(/^Copy\s+/, '') || 'configuration'
  );

  const copyPanel = async (button) => {
    const panel = button.closest('[role="tabpanel"]');
    const code = panel?.querySelector('pre code');
    if (!code) {
      return;
    }

    await copyText(code.textContent || '');
    button.textContent = 'Copied';
    announceCopyStatus(button, `${configurationNameFor(button)} copied.`);
    window.setTimeout(() => {
      button.textContent = 'Copy';
      announceCopyStatus(button, '');
    }, 1200);
  };

  const enhanceTabSet = (tabSet) => {
    if (tabSet.dataset.atlasFormatTabsEnhanced === 'true') {
      return;
    }

    tabSet.dataset.atlasFormatTabsEnhanced = 'true';
    const tabs = tabsFor(tabSet);
    const selected = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') || tabs[0];
    if (!selected) {
      return;
    }

    selectTab(tabSet, selected);
    tabSet.addEventListener('click', (event) => {
      if (!(event.target instanceof Element)) {
        return;
      }

      const tab = event.target.closest('[role="tab"]');
      if (tab && tabSet.contains(tab)) {
        selectTab(tabSet, tab);
        return;
      }

      const copy = event.target.closest('[data-atlas-copy]');
      if (copy && tabSet.contains(copy)) {
        void copyPanel(copy).catch(() => {
          copy.textContent = 'Retry';
          announceCopyStatus(copy, `Could not copy ${configurationNameFor(copy)}. Try again.`);
        });
      }
    });
    tabSet.addEventListener('keydown', (event) => {
      if (!(event.target instanceof Element)) {
        return;
      }

      const current = event.target.closest('[role="tab"]');
      if (!current || !tabSet.contains(current)) {
        return;
      }

      const currentIndex = tabs.indexOf(current);
      const nextByKey = {
        ArrowLeft: (currentIndex - 1 + tabs.length) % tabs.length,
        ArrowRight: (currentIndex + 1) % tabs.length,
        Home: 0,
        End: tabs.length - 1,
      };
      if (!(event.key in nextByKey)) {
        return;
      }

      event.preventDefault();
      selectTab(tabSet, tabs[nextByKey[event.key]], true);
    });
  };

  const enhance = (root = document) => {
    root.querySelectorAll(tabSets).forEach(enhanceTabSet);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => enhance());
  } else {
    enhance();
  }

  if (typeof document$ !== 'undefined') {
    document$.subscribe(enhance);
  }
})();
