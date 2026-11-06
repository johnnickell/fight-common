(() => {
  const tabSetSelector = '[data-atlas-format-tabs]';

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

    const fallbackTextarea = document.createElement('textarea');
    fallbackTextarea.value = text;
    fallbackTextarea.setAttribute('readonly', '');
    fallbackTextarea.style.position = 'fixed';
    fallbackTextarea.style.opacity = '0';
    document.body.append(fallbackTextarea);
    fallbackTextarea.select();
    const copied = document.execCommand('copy');
    fallbackTextarea.remove();
    if (!copied) {
      throw new Error('The browser did not allow copying this configuration.');
    }
  };

  const announceCopyStatus = (button, message) => {
    const status = button.closest(tabSetSelector)?.querySelector('[data-atlas-copy-status]');

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
    const selectedTab = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') || tabs[0];
    if (!selectedTab) {
      return;
    }

    selectTab(tabSet, selectedTab);
    tabSet.addEventListener('click', (event) => {
      if (!(event.target instanceof Element)) {
        return;
      }

      const tab = event.target.closest('[role="tab"]');
      if (tab && tabSet.contains(tab)) {
        selectTab(tabSet, tab);
        return;
      }

      const copyButton = event.target.closest('[data-atlas-copy]');
      if (copyButton && tabSet.contains(copyButton)) {
        void copyPanel(copyButton).catch(() => {
          copyButton.textContent = 'Retry';
          announceCopyStatus(copyButton, `Could not copy ${configurationNameFor(copyButton)}. Try again.`);
        });
      }
    });
    tabSet.addEventListener('keydown', (event) => {
      if (!(event.target instanceof Element)) {
        return;
      }

      const currentTab = event.target.closest('[role="tab"]');
      if (!currentTab || !tabSet.contains(currentTab)) {
        return;
      }

      const currentIndex = tabs.indexOf(currentTab);
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
    root.querySelectorAll(tabSetSelector).forEach(enhanceTabSet);
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
