// admin-ui-tintcal.js - 管理画面のUI（タブ・デモカレンダーなど）を操作する

(function(I18N) {
  document.addEventListener('DOMContentLoaded', () => {
    // i18n defaults (can be overridden by wp_localize_script -> window.tintcalI18n)
    Object.assign(I18N, (window.tintcalI18n || {
      weekdaysSunStart: ['日','月','火','水','木','金','土']
    }));

    // =============================
    // タブ切り替え処理
    // =============================

    const tabs = document.querySelectorAll('.nav-tab-wrapper .nav-tab');
    const tabContents = document.querySelectorAll('.tintcal-tab-content');

    const activateTabFromHash = () => {
      const hash = window.location.hash || '#tintcal-tab-1';
      const targetTab = document.querySelector(`.nav-tab[href="${hash}"]`);

      if (targetTab) {
        tabs.forEach(t => t.classList.remove('nav-tab-active'));
        tabContents.forEach(c => c.classList.remove('active'));
        targetTab.classList.add('nav-tab-active');
        const targetContent = document.querySelector(hash);
        if (targetContent) {
          targetContent.classList.add('active');

          // もし表示するタブが「カテゴリ・日付設定」で、まだカレンダーが初期化されていなければ
          if (hash === '#tintcal-tab-2') {
              
              if (typeof window.initializeCalendarEditor === 'function') {
                  window.initializeCalendarEditor();
              }
          }
        }
      }
    };
    
    tabs.forEach(tab => {
      tab.addEventListener('click', (e) => {
        e.preventDefault();
        if (window.location.hash !== tab.hash) {
          window.location.hash = tab.hash;
        }
      });
    });

    activateTabFromHash();
    window.addEventListener('hashchange', activateTabFromHash);


    // =============================
    // デモカレンダーのリアルタイム更新処理
    // =============================
    const demoContainer = document.querySelector('.tintcal-settings-demo');
    if (demoContainer) {
      // --- 各種設定入力要素を取得 ---
      const settingsForm = document.querySelector('.tintcal-settings-form');
      const headerColorInput = document.getElementById('tintcal-header-color-input');
      const sundayColorInput = document.getElementById('tintcal-sunday-color-input');
      const saturdayColorInput = document.getElementById('tintcal-saturday-color-input');
      const holidayColorInput = document.getElementById('tintcal-holiday-color-input');
      const legendCheckbox = document.getElementById('tintcal-legend-toggle-input');
      const enableHolidaysCheckbox = document.querySelector('input[name="enable_holidays"]');
      const showSundayColorCheckbox = document.querySelector('input[name="show_sunday_color"]');
      const showSaturdayColorCheckbox = document.querySelector('input[name="show_saturday_color"]');
      const startDayInputs = document.querySelectorAll('input[name="start_day"]');
      const todayButtonCheckbox = document.getElementById('tintcal-show-today-button-input');
      const demoTodayBtn = document.getElementById('tintcal-demo-today-btn');

      // --- デモカレンダー本体を取得 ---
      const demoCalendar = document.getElementById('tintcal-demo-calendar');
      const demoLegend = document.getElementById('tintcal-demo-legend');

      // --- デモカレンダーを動的に描画する関数 ---
      const renderDemoCalendar = () => {
        const checked = document.querySelector('input[name="start_day"]:checked');
        const startDay = checked ? checked.value : 'sunday';
        const weekdaysSunStart = [
          { name: I18N.weekdaysSunStart[0], day: 'sun' }, { name: I18N.weekdaysSunStart[1], day: 'mon' }, { name: I18N.weekdaysSunStart[2], day: 'tue' },
          { name: I18N.weekdaysSunStart[3], day: 'wed' }, { name: I18N.weekdaysSunStart[4], day: 'thu' }, { name: I18N.weekdaysSunStart[5], day: 'fri' },
          { name: I18N.weekdaysSunStart[6], day: 'sat' }
        ];
        const weekdays = (startDay === 'monday') ? [...weekdaysSunStart.slice(1), weekdaysSunStart[0]] : weekdaysSunStart;
        
        demoCalendar.innerHTML = ''; // 中身をクリア

        // ヘッダーを描画
        weekdays.forEach(dayInfo => {
          const header = document.createElement('div');
          header.className = `tintcal-demo-header`;
          // ヘッダーにも曜日クラスを付与
          if (dayInfo.day === 'sun') header.classList.add('tintcal-demo-sunday');
          if (dayInfo.day === 'sat') header.classList.add('tintcal-demo-saturday');
          header.textContent = dayInfo.name;
          demoCalendar.appendChild(header);
        });

        // ダミーの日付セルを描画（ここでは35セル固定で描画）
        // 1日が水曜日始まりのダミーカレンダー
        const startOffset = (startDay === 'monday') ? 2 : 3;
        for (let i = 0; i < 35; i++) {
          const cell = document.createElement('div');
          cell.className = 'tintcal-demo-cell';
          const date = i - startOffset + 1;

          if (date > 0 && date <= 31) {
            cell.textContent = date;
            
            // ▼▼▼ 曜日クラスを判定する計算式を修正 ▼▼▼
            const dayOfWeekIndex = i % 7; // 0=1番目, 1=2番目, ... 6=7番目

            if (startDay === 'monday') {
              // 月曜始まりの場合、5番目が土曜、6番目が日曜
              if (dayOfWeekIndex === 5) cell.classList.add('tintcal-demo-saturday');
              if (dayOfWeekIndex === 6) cell.classList.add('tintcal-demo-sunday');
            } else {
              // 日曜始まりの場合、0番目が日曜、6番目が土曜
              if (dayOfWeekIndex === 0) cell.classList.add('tintcal-demo-sunday');
              if (dayOfWeekIndex === 6) cell.classList.add('tintcal-demo-saturday');
            }

            // 祝日デモの日付
            if (date === 15) cell.classList.add('tintcal-demo-holiday');
          }
          demoCalendar.appendChild(cell);
        }
      };

      // --- デモカレンダーの色や表示を更新する関数 ---
      const showHeaderWeekendColorCheckbox = document.getElementById('tintcal-header-weekend-color-toggle');
      const updateDemoAppearance = () => {
        // 1. まずPHPから渡されたデータを正とする
        const baseSettings = window.tintcalPluginData || {};

        // 2. ページ上の入力欄から現在の値を取得（存在すれば、その値を優先する）
        const liveSettings = {
            headerColor: headerColorInput ? headerColorInput.value : baseSettings.headerColor,
            sundayColor: sundayColorInput ? sundayColorInput.value : baseSettings.sundayColor,
            saturdayColor: saturdayColorInput ? saturdayColorInput.value : baseSettings.saturdayColor,
            holidayColor: holidayColorInput ? holidayColorInput.value : baseSettings.holidayColor,
            showLegend: legendCheckbox ? legendCheckbox.checked : baseSettings.showLegend,
            enableHolidays: enableHolidaysCheckbox ? enableHolidaysCheckbox.checked : baseSettings.enableHolidays,
            showSundayColor: showSundayColorCheckbox ? showSundayColorCheckbox.checked : baseSettings.showSundayColor,
            showSaturdayColor: showSaturdayColorCheckbox ? showSaturdayColorCheckbox.checked : baseSettings.showSaturdayColor,
            showHeaderWeekendColor: showHeaderWeekendColorCheckbox ? showHeaderWeekendColorCheckbox.checked : baseSettings.showHeaderWeekendColor,
            showTodayButton: todayButtonCheckbox ? todayButtonCheckbox.checked : baseSettings.showTodayButton
        };

        // 3. 2つの設定をマージして、この関数内で使う最終的な設定オブジェクトを作成
        const settings = { ...baseSettings, ...liveSettings };
        const defaultCellBg = '#fff';

        // 1. ヘッダーの色を更新
        demoCalendar.querySelectorAll('.tintcal-demo-header').forEach(h => {
              h.style.backgroundColor = settings.headerColor;
              // 「曜日ヘッダーを色付け」がONの場合のみ、週末色を適用
              if (Number(settings.showHeaderWeekendColor)) {
                  if (h.classList.contains('tintcal-demo-sunday')) {
                      h.style.backgroundColor = settings.sundayColor;
                  }
                  if (h.classList.contains('tintcal-demo-saturday')) {
                      h.style.backgroundColor = settings.saturdayColor;
                  }
              }
        });
        
        // 2. 日付セルの色を更新
        demoCalendar.querySelectorAll('.tintcal-demo-cell').forEach(cell => {
            let bgColor = defaultCellBg;

            if (cell.classList.contains('tintcal-demo-sunday') && Number(settings.showSundayColor)) {
                bgColor = settings.sundayColor;
            }
            if (cell.classList.contains('tintcal-demo-saturday') && Number(settings.showSaturdayColor)) {
                bgColor = settings.saturdayColor;
            }
            
            if (cell.classList.contains('tintcal-demo-holiday') && Number(settings.enableHolidays)) {
                bgColor = settings.holidayColor;
            }

            cell.style.backgroundColor = bgColor;
        });
        
        // 3. 凡例表示の更新
        if(demoLegend) demoLegend.style.display = Number(settings.showLegend) ? 'block' : 'none';

        // 4. 「今月に戻る」ボタンのデモ表示更新
        if(demoTodayBtn) demoTodayBtn.style.display = Number(settings.showTodayButton) ? 'inline-block' : 'none';
        
      };

      // 週の開始曜日が変更されたら、カレンダー構造から再描画
      if (startDayInputs && typeof startDayInputs.forEach === 'function') {
        startDayInputs.forEach(radio => {
            radio.addEventListener('change', () => {
                renderDemoCalendar();
                updateDemoAppearance();
            });
        });
      }

      // その他の設定は、色の更新だけを行う
      if (settingsForm) {
        settingsForm.addEventListener('input', updateDemoAppearance);
      }
      
      // --- 初期表示 ---
      renderDemoCalendar();
      updateDemoAppearance();
    }
  });
})(window.I18N = window.I18N || {});
