<?php
declare(strict_types=1);
?>
<script>
(function () {
    function initializeListSectionToggles() {
        const sections = document.querySelectorAll('[data-list-section]');

        function setSectionVisibility(sectionName, isVisible) {
            const section = document.querySelector('[data-list-section="' + sectionName + '"]');
            if (!section) {
                return;
            }

            section.style.display = isVisible ? 'flex' : 'none';
        }

        function handleToggleClick(event, isVisible) {
            const sectionName = event.currentTarget.getAttribute(isVisible ? 'data-list-section-trigger' : 'data-list-section-hide');
            if (!sectionName) {
                return;
            }

            setSectionVisibility(sectionName, isVisible);
        }

        function handleToggleKeydown(event, isVisible) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            handleToggleClick(event, isVisible);
        }

        sections.forEach(function (section) {
            section.style.display = 'none';
        });

        document.querySelectorAll('[data-list-section-trigger]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                handleToggleClick(event, true);
            });
            trigger.addEventListener('keydown', function (event) {
                handleToggleKeydown(event, true);
            });
        });

        document.querySelectorAll('[data-list-section-hide]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                handleToggleClick(event, false);
            });
            trigger.addEventListener('keydown', function (event) {
                handleToggleKeydown(event, false);
            });
        });
    }

    initializeListSectionToggles();
})();
</script>
