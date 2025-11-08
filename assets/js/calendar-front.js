// assets/js/calendar-front.js

// i18n defaults (override via wp_localize_script -> window.tintcalI18n)
const I18N = (window.tintcalI18n || {
  unexpectedResponse: '予期しない応答',
  failedToSaveHolidays: '祝日データの保存に失敗しました。',
  ajaxError: 'Ajax通信エラー',
  reloadPage: '祝日データの取得中に問題が発生しました。再読み込みしてください。'
});

// 祝日データは TintCal クラスのプロパティとして管理するため、グローバルな宣言は不要


function saveHolidaysToLocal(year, holidays) {

  fetch(tintcalPluginData.ajaxUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: new URLSearchParams({
      action: "save_tintcal_holidays",
      year: year,
      holidays: JSON.stringify(holidays),
      locale: tintcalPluginData.locale || 'ja',
      _ajax_nonce: tintcalPluginData.nonce
    })
  })
  .then(res => {
    if (!res.ok) {
      console.warn("⚠️ " + I18N.unexpectedResponse + ":", res.status, res.statusText);
    }
    return res.json();
  })
  .then(result => {
    if (result.success) {
    } else {
      console.warn("❌ " + I18N.failedToSaveHolidays + ":", result);
    }
  })
  .catch(err => {
    console.error("❌ " + I18N.ajaxError + ":", err, "at", new Date().toLocaleString());
    alert(I18N.reloadPage);
  });
}
window.saveHolidaysToLocal = saveHolidaysToLocal;

/**
 * TintCalの各インスタンスを管理するクラス
 */
class TintCal {
    /**
     * @param {string} selector - カレンダーのコンテナ要素を特定するCSSセレクタ (例: '#tintcal-container-123-abc')
     * @param {object} settings - PHPから渡される、このカレンダー専用の設定オブジェクト
     */
    constructor(selector, settings) {
        this.container = document.querySelector(selector);
        if (!this.container) {
            console.error(`TintCal Error: Container element "${selector}" not found.`);
            return;
        }


        // --- プロパティの初期化 ---
        this.settings = settings;
        this.currentYear = new Date().getFullYear();
        this.currentMonth = new Date().getMonth();

        // --- DOM要素の取得 ---
        this.monthYearEl = this.container.querySelector('.tintcal-month-year');
        this.prevBtn = this.container.querySelector('.prev-month');
        this.nextBtn = this.container.querySelector('.next-month');
        this.todayBtn = this.container.querySelector('.back-to-today');
        this.calendarBody = this.container.querySelector('.tintcal-calendar tbody');
        this.calendarHead = this.container.querySelector('.tintcal-calendar thead tr');
        this.legendContainer = this.container.querySelector('.calendar-legend');
        
        // 初期化処理を実行
        this.init();
    }

    /**
     * カレンダーの初期化
     */
    async init() { // asyncを追加
    this.attachMonthNavigation();
    await this.drawCalendar(); // awaitを追加
    }

    /**
     * 月送りナビゲーションのイベントリスナーを設定
     */
    attachMonthNavigation() {
        this.prevBtn.addEventListener('click', () => this.changeMonth(-1));
        this.nextBtn.addEventListener('click', () => this.changeMonth(1));
        if (this.todayBtn) {
            this.todayBtn.addEventListener('click', () => this.goToday());
        }
    }

    /**
     * 月を変更する
     * @param {number} direction -1で前月、1で次月
     */
    async changeMonth(direction) { // asyncを追加（または確認）
        this.currentMonth += direction;
        if (this.currentMonth < 0) {
            this.currentMonth = 11;
            this.currentYear--;
        }
        if (this.currentMonth > 11) {
            this.currentMonth = 0;
            this.currentYear++;
        }
        await this.drawCalendar(); // awaitを追加
    }
    
    /**
     * 今月に戻る
     */
    async goToday() { // asyncを追加（または確認）
        const today = new Date();
        this.currentYear = today.getFullYear();
        this.currentMonth = today.getMonth();
        await this.drawCalendar(); // awaitを追加
    }

    /**
     * 指定された年の祝日データを取得・キャッシュする（未取得の場合のみ）
     * ローカルになければ外部APIを試す、より堅牢なバージョン
     * @param {number} year - 取得する年
     * @returns {Promise<void>}
     */
    async fetchAndCacheYearlyHolidays(year) {
        if (this.settings.holidays && typeof this.settings.holidays[year] !== 'undefined') {
            return; // メモリにデータがあれば何もしない
        }

        const locale = this.settings.locale || 'ja';
        const localUrl = `${this.settings.holidayJsonUrl}/${locale}/${year}.json`;

        try {
            // 1. まずローカルのJSONファイルを試す
            const localRes = await fetch(localUrl);
            if (localRes.ok) {
                this.settings.holidays[year] = await localRes.json();
                return; // 成功したので終了
            }

            // 2. ローカルになければ外部APIを試す (日本の場合のみ)
            if (locale === 'ja') {
                const externalUrl = `https://holidays-jp.github.io/api/v1/${year}/date.json`;
                const externalRes = await fetch(externalUrl);
                if (externalRes.ok) {
                    const holidayJson = await externalRes.json();
                    this.settings.holidays[year] = holidayJson;

                    // 取得したデータをサーバーに保存し直す
                    if (typeof window.saveHolidaysToLocal === 'function') {
                        window.saveHolidaysToLocal(year, holidayJson);
                    }
                } else {
                    this.settings.holidays[year] = {};
                }
            } else {
                this.settings.holidays[year] = {};
            }
    
        } catch (e) {
            console.error(`${year}年の祝日データ取得に失敗:`, e);
            this.settings.holidays[year] = {}; // エラー時も空として扱う
        }
    }

    /**
     * カレンダーのHTMLを描画する
     */
    async drawCalendar() { // 1. async を追加
        // 2. ▼▼▼ 描画の前に、この年の祝日データがあるか確認・取得 ▼▼▼
        await this.fetchAndCacheYearlyHolidays(this.currentYear);

        const { settings, currentYear, currentMonth } = this;

        const { categories = [], assignments = {}, startDay = 'sunday' } = settings;
        
        // --- ヘッダー描画 ---
        const weekdayOffset = (startDay === 'monday') ? 1 : 0;
        const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        const reorderedWeekdays = [...weekdays.slice(weekdayOffset), ...weekdays.slice(0, weekdayOffset)];
        
        this.calendarHead.innerHTML = '';
        reorderedWeekdays.forEach((day, index) => {
            const th = document.createElement('th');
            th.textContent = day;
            th.style.backgroundColor = settings.headerColor;
            if (Number(settings.showHeaderWeekendColor) === 1) {
                const originalIndex = (index + weekdayOffset) % 7;
                if (originalIndex === 0) th.style.backgroundColor = settings.sundayColor;
                if (originalIndex === 6) th.style.backgroundColor = settings.saturdayColor;
            }
            this.calendarHead.appendChild(th);
        });
        
        // --- 日付セル描画（DocumentFragmentでDOM操作を最適化） ---
        this.calendarBody.innerHTML = '';
        this.monthYearEl.textContent = `${currentYear}年${currentMonth + 1}月`;
        const firstDay = new Date(currentYear, currentMonth, 1).getDay();
        const startOffset = (firstDay - weekdayOffset + 7) % 7;
        const lastDate = new Date(currentYear, currentMonth + 1, 0).getDate();

        const fragment = document.createDocumentFragment();
        let date = 1;
        for (let i = 0; i < 6; i++) {
            const row = document.createElement('tr');
            for (let j = 0; j < 7; j++) {
                const cell = document.createElement('td');
                if (i === 0 && j < startOffset || date > lastDate) {
                    // 空セル
                } else {
                    const dateString = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
                    const dayOfWeek = new Date(currentYear, currentMonth, date).getDay();
                    let titleText = "";
                    cell.textContent = date;

                    // 週末の色設定
                    // チェックボックスの値が "1" や 1 のときだけ色を付ける（"0"や0はOFFになる）
                    if (Number(settings.showSundayColor) === 1 && dayOfWeek === 0) {
                        cell.style.backgroundColor = settings.sundayColor;
                    }
                    if (Number(settings.showSaturdayColor) === 1 && dayOfWeek === 6) {
                        cell.style.backgroundColor = settings.saturdayColor;
                    }

                    // 祝日の色設定
                    // 今年の祝日データを取り出すように変更
                    const yearHolidays = this.settings.holidays[currentYear] || {};
                    if (Number(settings.enableHolidays) === 1 && yearHolidays[dateString]) {
                        cell.style.backgroundColor = settings.holidayColor;
                        titleText = yearHolidays[dateString];
                    }

                    // カテゴリの色設定（単一カテゴリ製品）
                    let assigned = assignments[dateString];
                    // 配列の場合は先頭のみ、文字列ならそのまま採用
                    const assignedSlug = Array.isArray(assigned) ? assigned[0] : (assigned || null);
                    if (assignedSlug) {
                        const visibleSlugs = settings.visibleCategories || [];
                        const matched = categories
                          .filter(cat => cat.slug === assignedSlug && (visibleSlugs.length === 0 || visibleSlugs.includes(cat.slug)));
                        if (matched.length > 0) {
                            const cat = matched[0];
                            cell.style.backgroundColor = cat.color;
                            // 祝日名などが既にあれば区切り文字を追加
                            if (titleText) titleText += ' / ';
                            titleText += cat.name;
                        }
                    }
                    if (titleText) cell.title = titleText;
                    date++;
                }
                row.appendChild(cell);
            }
            fragment.appendChild(row);
        }
        this.calendarBody.appendChild(fragment);
        this.renderLegend();
    }

    /**
     * 凡例を描画する
     */
    renderLegend() {
        if (!this.legendContainer) return;
        const { settings } = this;

        if (Number(settings.showLegend) === 1) {
            this.legendContainer.style.display = 'flex';
        } else {
            this.legendContainer.style.display = 'none';
            return;
        }
        this.legendContainer.style.flexWrap = 'wrap';
        this.legendContainer.innerHTML = '';

        // 凡例に表示するカテゴリを決定
        let categoriesForLegend = (settings.categories || []); // まずは全てのカテゴリを取得

        // 設定で表示が許可されたカテゴリのスラグ配列が存在し、かつ空でない場合のみフィルタリング
        if (Array.isArray(settings.visibleCategories) && settings.visibleCategories.length > 0) {
            categoriesForLegend = categoriesForLegend.filter(cat => settings.visibleCategories.includes(cat.slug));
        }
        // その他の条件（例: cat.visible === false）でフィルタリングが必要ならここに追加
        categoriesForLegend = categoriesForLegend.filter(cat => cat.visible !== false);

        // 順番でソート
        categoriesForLegend.sort((a, b) => (a.order ?? 999) - (b.order ?? 999));

        categoriesForLegend.forEach(cat => { // ★ ここを修正
            const item = document.createElement('div');
            // CSSクラスではなく、直接スタイルを指定する
            item.style.display = 'inline-flex';
            item.style.alignItems = 'center';
            item.style.marginRight = '15px';
            item.style.marginBottom = '5px';
            // 色チップの見た目もインラインスタイルで完全に指定
            item.innerHTML = `<span style="display:inline-block;width:12px;height:12px;background-color:${cat.color};margin-right:5px;border:1px solid #ccc;border-radius:3px;"></span><span>${cat.name}</span>`;
            this.legendContainer.appendChild(item);
        });
    }
}
