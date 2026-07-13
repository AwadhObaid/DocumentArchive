(() => {
    const byId = (id) => document.getElementById(id);

    const startInput = byId('leaveStartDate');
    const endInput = byId('leaveEndDate');
    const includeStartInput = byId('leaveIncludeStart');
    const holidaysInput = byId('leaveOfficialHolidays');
    const errorBox = byId('leaveCalculatorError');

    if (!startInput || !endInput) {
        return;
    }

    const ids = {
        years: byId('leaveYears'),
        months: byId('leaveMonths'),
        days: byId('leaveDays'),
        totalDays: byId('leaveTotalDays'),
        weekendDays: byId('leaveWeekendDays'),
        officialDays: byId('leaveOfficialDays'),
        netDays: byId('leaveNetDays'),
        grandTotal: byId('leaveGrandTotal'),
    };

    const parseDate = (value) => {
        if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return null;
        }

        const [year, month, day] = value.split('-').map(Number);
        const date = new Date(year, month - 1, day);

        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
            return null;
        }

        return date;
    };

    const cloneDate = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());

    const addDays = (date, days) => {
        const result = cloneDate(date);
        result.setDate(result.getDate() + days);
        return result;
    };

    const toKey = (date) => {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    };

    const dayDiff = (start, endExclusive) => {
        const ms = cloneDate(endExclusive).getTime() - cloneDate(start).getTime();
        return Math.max(0, Math.round(ms / 86400000));
    };

    const calendarDiff = (start, endExclusive) => {
        if (endExclusive < start) {
            return { years: 0, months: 0, days: 0 };
        }

        let years = endExclusive.getFullYear() - start.getFullYear();
        let months = endExclusive.getMonth() - start.getMonth();
        let days = endExclusive.getDate() - start.getDate();

        if (days < 0) {
            months -= 1;
            const previousMonthLastDay = new Date(endExclusive.getFullYear(), endExclusive.getMonth(), 0).getDate();
            days += previousMonthLastDay;
        }

        if (months < 0) {
            years -= 1;
            months += 12;
        }

        return {
            years: Math.max(0, years),
            months: Math.max(0, months),
            days: Math.max(0, days),
        };
    };

    const selectedWeekendDays = () => {
        return new Set(Array.from(document.querySelectorAll('.leaveWeekendDay:checked')).map((item) => Number(item.value)));
    };

    const officialHolidaySet = () => {
        const values = (holidaysInput?.value || '')
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter(Boolean)
            .filter((line) => /^\d{4}-\d{2}-\d{2}$/.test(line));

        return new Set(values);
    };

    const setNumber = (key, value) => {
        if (ids[key]) {
            ids[key].textContent = String(value);
        }
    };

    const showError = (message) => {
        if (!errorBox) return;
        errorBox.textContent = message;
        errorBox.hidden = false;
    };

    const clearError = () => {
        if (!errorBox) return;
        errorBox.textContent = '';
        errorBox.hidden = true;
    };

    const resetResults = () => {
        Object.keys(ids).forEach((key) => setNumber(key, 0));
    };

    const calculate = () => {
        clearError();

        const start = parseDate(startInput.value);
        const end = parseDate(endInput.value);

        if (!start || !end) {
            resetResults();
            showError('يرجى اختيار تاريخ البداية وتاريخ النهاية بشكل صحيح.');
            return;
        }

        if (end < start) {
            resetResults();
            showError('تاريخ النهاية يجب أن يكون مساويًا أو أكبر من تاريخ البداية.');
            return;
        }

        const endExclusive = includeStartInput?.checked ? addDays(end, 1) : cloneDate(end);
        const totalDays = dayDiff(start, endExclusive);
        const diff = calendarDiff(start, endExclusive);
        const weekendDays = selectedWeekendDays();
        const holidays = officialHolidaySet();

        let weekendCount = 0;
        let officialCount = 0;

        for (let cursor = cloneDate(start); cursor < endExclusive; cursor = addDays(cursor, 1)) {
            const isWeekend = weekendDays.has(cursor.getDay());
            const isOfficialHoliday = holidays.has(toKey(cursor));

            if (isWeekend) {
                weekendCount += 1;
            }

            if (isOfficialHoliday && !isWeekend) {
                officialCount += 1;
            }
        }

        const netDays = Math.max(0, totalDays - weekendCount - officialCount);

        setNumber('years', diff.years);
        setNumber('months', diff.months);
        setNumber('days', diff.days);
        setNumber('totalDays', totalDays);
        setNumber('weekendDays', weekendCount);
        setNumber('officialDays', officialCount);
        setNumber('netDays', netDays);
        setNumber('grandTotal', totalDays);
    };

    const todayKey = () => toKey(new Date());

    byId('leaveCalculateButton')?.addEventListener('click', calculate);
    byId('leaveResetButton')?.addEventListener('click', () => {
        startInput.value = todayKey();
        endInput.value = todayKey();
        if (includeStartInput) includeStartInput.checked = false;
        if (holidaysInput) holidaysInput.value = '';
        document.querySelectorAll('.leaveWeekendDay').forEach((input) => {
            input.checked = input.value === '5' || input.value === '6';
        });
        clearError();
        resetResults();
    });
    byId('leaveTodayButton')?.addEventListener('click', () => {
        startInput.value = todayKey();
        endInput.value = todayKey();
        calculate();
    });

    [startInput, endInput, includeStartInput, holidaysInput, ...document.querySelectorAll('.leaveWeekendDay')]
        .filter(Boolean)
        .forEach((input) => input.addEventListener('change', calculate));

    calculate();
})();
