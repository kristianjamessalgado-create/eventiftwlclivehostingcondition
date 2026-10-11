(function () {
    var form = document.getElementById('attReportFilterForm');
    if (!form) {
        return;
    }

    var dept = document.getElementById('attFilterDept');
    var course = document.getElementById('attFilterCourse');
    var year = document.getElementById('attFilterYear');
    var section = document.getElementById('attFilterSection');

    function applyCourseVisibility() {
        if (!course || !dept) {
            return;
        }
        var selectedDept = String(dept.value || '');
        Array.prototype.forEach.call(course.options, function (opt) {
            if (!opt.value) {
                opt.hidden = false;
                return;
            }
            var optDept = String(opt.getAttribute('data-department') || '');
            opt.hidden = selectedDept !== '' && optDept !== '' && optDept !== selectedDept;
        });
        if (course.value) {
            var selected = course.options[course.selectedIndex];
            if (selected && selected.hidden) {
                course.value = '';
            }
        }
    }

    applyCourseVisibility();

    if (dept) {
        dept.addEventListener('change', function () {
            if (course) {
                course.value = '';
            }
            applyCourseVisibility();
            form.submit();
        });
    }

    [course, year, section].forEach(function (el) {
        if (!el) {
            return;
        }
        el.addEventListener('change', function () {
            form.submit();
        });
    });

    var printBtn = document.getElementById('attPrintBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function (e) {
            e.preventDefault();
            window.print();
        });
    }
})();
