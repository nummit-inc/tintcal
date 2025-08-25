// assets/js/calendar.js（Step 7：DB保存対応版 - localStorageからAjaxへ移行）

// =============================
// 定数・グローバル変数の定義
// =============================
window.saveHolidaysToLocal = saveHolidaysToLocal;

var currentYear, currentMonth;
// holidayCache のグローバル宣言は削除
const isAdminScreen =
  window.location.href.includes("page=tintcal-settings") ||
  window.location.href.includes("page=tintcal-preference") ||
  window.location.href.includes("post.php?post=");

const HOLIDAY_REFRESH_MONTHS = 6;

/** i18n defaults (override via wp_localize_script -> window.tintcalI18n) */
// グローバルなI18Nオブジェクトを安全に初期化します
const I18N = (window.I18N = window.I18N || {});
Object.assign(I18N, (window.tintcalI18n || {
  // generic
  loading: "取得中...",
  reloadHoliday: "祝日データを再取得",
  // sidebar/help
  pleaseCreateCategory: "まずは「カテゴリ追加・編集」でカテゴリを登録してください。",
  // import messages
  importSuccess: "カテゴリと日付データをインポートしました。",
  importError: "インポートに失敗しました。JSONの形式を確認してください。",
  pleaseSelectFile: "ファイルを選択してください。",
  // save/assign modal
  saved: "保存しました",
  failedToSave: "保存に失敗しました。",
  // reset confirmations / results
  resetDatesConfirm: "すべてのカレンダー日付割当を初期化します。よろしいですか？",
  resetDatesOk: "日付の割当を初期化しました",
  resetFailed: "初期化に失敗しました。",
  resetError: "初期化中にエラーが発生しました。",
  resetAllConfirm: "この操作は元に戻せません。本当に全データを初期化しますか？",
  resetAllOk: "全データを初期化しました",
  // holiday reload messages
  holidayUpdatedHeader: "<strong>祝日データの更新が完了しました。</strong>",
  yearUpdated: (year) => `✅ ${year}年: 更新しました。`,
  yearFailed: (year, err) => `❌ ${year}年: 失敗しました (${err})`,
  fetchHolidayFailed: "祝日データの再取得に失敗しました。",
  fetchHolidayError: "祝日再取得中にエラーが発生しました。",
  // saveHolidaysToLocal()
  unexpectedResponse: "予期しない応答",
  failedToSaveHolidays: "祝日データの保存に失敗しました。",
  ajaxError: "Ajax通信エラー",
  reloadPage: "祝日データの取得中に問題が発生しました。再読み込みしてください。"
}));

// =============================
// ユーティリティ関数
// =============================


function loadDateAssignments() {
  try {
    return window.tintcalPluginData?.assignments || {};
  } catch (e) {
    return {};
  }
}

function showTemporaryMessage(msg, duration = 2000) {
  const box = document.createElement("div");
  box.innerHTML = msg;
  box.style.position = "fixed";
  box.style.top = "20px";
  box.style.right = "20px";
  box.style.background = "#dff0d8";
  box.style.color = "#3c763d";
  box.style.padding = "8px 12px";
  box.style.border = "1px solid #d6e9c6";
  box.style.borderRadius = "4px";
  box.style.zIndex = 9999;
  document.body.appendChild(box);
  setTimeout(() => {
    document.body.removeChild(box);
  }, duration);
}

// =============================
// Ajax通信・保存系関数
// =============================


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


/**
 * 指定された年の祝日データを取得・キャッシュする（未取得の場合のみ）
 * ローカルになければ外部APIを試す、より堅牢なバージョン
 * @param {number} year - 取得する年
 * @returns {Promise<void>}
 */
async function fetchAndCacheYearlyHolidays(year) {
    const holidaysData = window.tintcalPluginData.holidays || {};
    if (typeof holidaysData[year] !== 'undefined') {
        return; // メモリにデータがあれば何もしない
    }

    const locale = window.tintcalPluginData.locale || 'ja';
    const localUrl = `${window.tintcalPluginData.holidayJsonUrl}/${locale}/${year}.json`;

    try {
        // 1. まずローカルのJSONファイルを試す
        const localRes = await fetch(localUrl);
        if (localRes.ok) {
            window.tintcalPluginData.holidays[year] = await localRes.json();
            return; // 成功したので終了
        }

        // 2. ローカルになければ外部APIを試す (日本の場合のみ)
        if (locale === 'ja') {
            const externalUrl = `https://holidays-jp.github.io/api/v1/${year}/date.json`;
            const externalRes = await fetch(externalUrl);
            if (externalRes.ok) {
                const holidayJson = await externalRes.json(); // 一時変数に取得
                window.tintcalPluginData.holidays[year] = holidayJson; // メモリ上のデータを更新

                // 取得したデータを、サーバーに保存し直すよう依頼する
                if (typeof window.saveHolidaysToLocal === 'function') {
                    window.saveHolidaysToLocal(year, holidayJson);
                }
                
            } else {
                // 外部APIにもなければ「データなし」と記憶
                window.tintcalPluginData.holidays[year] = {};
            }
        } else {
            // 日本以外の場合は外部APIがないので「データなし」とする
            window.tintcalPluginData.holidays[year] = {};
        }

    } catch (e) {
        console.error(`${year}年の祝日データ取得に失敗:`, e);
        window.tintcalPluginData.holidays[year] = {}; // エラー時も空として扱う
    }
}

// =============================
// カレンダー描画・UI系関数
// =============================
async function drawCalendar() { // 1. async を追加
  
  // 2. ▼▼▼ 描画の前に、この年の祝日データがあるか確認・取得 ▼▼▼
  await fetchAndCacheYearlyHolidays(currentYear);

  const calendarContainerInner = document.querySelector(".tintcal-calendar-container-inner");
  if (!calendarContainerInner) {
    console.warn("drawCalendar: .tintcal-calendar-container-inner not found, skipping render.");
    return;
  }

  var calendarBody = calendarContainerInner.querySelector(".tintcal-calendar tbody");
  var calendarHead = calendarContainerInner.querySelector(".tintcal-calendar thead tr");
  const startDay = calendarContainerInner.querySelector(".tintcal-start-day")?.value || "sunday";
  const monthYearEl = calendarContainerInner.querySelector('.tintcal-month-year');
  const enableHolidays = document.getElementById("tintcal-enable-holidays")?.value === "1";
  const holidayColor = document.getElementById("tintcal-holiday-color")?.value || "#ffdddd";
  const yearHolidays = tintcalPluginData.holidays[currentYear] || {};
        if (enableHolidays && yearHolidays[key]) {
          cell.style.backgroundColor = holidayColor;
          titleText = yearHolidays[key];
        }
  const weekdayOffset = startDay === "monday" ? 1 : 0;
  const weekdays = ["日", "月", "火", "水", "木", "金", "土"];
  const reorderedWeekdays = weekdays.slice(weekdayOffset).concat(weekdays.slice(0, weekdayOffset));
  window.tintcalPluginData.categories = Array.isArray(window.tintcalPluginData.categories)
  ? window.tintcalPluginData.categories
  : Object.values(window.tintcalPluginData.categories || {});
  let userCategories = window.tintcalPluginData.categories;

  const dateAssignments = window.tintcalPluginData?.assignments || {};
  const colors = { ...userCategories };

  // ▼▼▼ ヘッダー描画のロジック ▼▼▼

  calendarHead.innerHTML = ""; // ヘッダー行をクリア

  // 設定値を取得
  const headerColor = tintcalPluginData.headerColor || "#eeeeee";
  const sundayColor = tintcalPluginData.sundayColor || "#ffecec";
  const saturdayColor = tintcalPluginData.saturdayColor || "#ecf5ff";
  const showHeaderColor = tintcalPluginData.showHeaderWeekendColor == 1; // 新しい設定

  reorderedWeekdays.forEach(day => {
    const th = document.createElement("th");
    th.textContent = day;

    const dayIndex = weekdays.indexOf(day); // 元の配列でのインデックスを取得 (0=日, 6=土)
    
    // まずはデフォルトのヘッダー色を適用
    th.style.backgroundColor = headerColor;

    // 「曜日ヘッダーを色付け」がONの場合のみ、週末色で上書き
    if (showHeaderColor) {
      if (dayIndex === 0) { // 日曜
        th.style.backgroundColor = sundayColor;
      } else if (dayIndex === 6) { // 土曜
        th.style.backgroundColor = saturdayColor;
      }
    }
    
    calendarHead.appendChild(th);
  });

  calendarBody.innerHTML = "";
  const firstDay = new Date(currentYear, currentMonth, 1).getDay();
  const startOffset = (firstDay - weekdayOffset + 7) % 7;
  const lastDate = new Date(currentYear, currentMonth + 1, 0).getDate();

monthYearEl.textContent = `${currentYear}年${currentMonth + 1}月`;

  let date = 1;
  for (let i = 0; i < 6; i++) {
    const row = document.createElement("tr");
    for (let j = 0; j < 7; j++) {
      const cell = document.createElement("td");
      if (i === 0 && j < startOffset || date > lastDate) {
        cell.textContent = "";
      } else {
        const mm = String(currentMonth + 1).padStart(2, "0");
        const dd = String(date).padStart(2, "0");
        const key = `${currentYear}-${mm}-${dd}`;
        cell.textContent = date;
        cell.setAttribute("data-date", key);

        const sundayColor = tintcalPluginData.sundayColor || "#ffecec";
        const saturdayColor = tintcalPluginData.saturdayColor || "#ecf5ff";
        const showSunday = tintcalPluginData.showSundayColor == 1; // 文字列'1'と数値1の両方に対応
        const showSaturday = tintcalPluginData.showSaturdayColor == 1;

        const cellDate = new Date(currentYear, currentMonth, date);
        const dayOfWeek = cellDate.getDay();

        // まず週末の色を適用
        if (dayOfWeek === 0 && showSunday) {
          cell.style.backgroundColor = sundayColor;
        }
        if (dayOfWeek === 6 && showSaturday) {
          cell.style.backgroundColor = saturdayColor;
        }

        let titleText = "";

        // 祝日であれば色を上書き（祝日が優先）
        const enableHolidays = tintcalPluginData.enableHolidays == 1;
        const holidayColor = tintcalPluginData.holidayColor || "#ffdddd";
        // 3. ▼▼▼ 年ごとの箱から、今年の祝日データを取り出すように変更 ▼▼▼
        const yearHolidays = tintcalPluginData.holidays[currentYear] || {};
        if (enableHolidays && yearHolidays[key]) {
          cell.style.backgroundColor = holidayColor;
          titleText = yearHolidays[key];
        }

        // カテゴリ割当あり・かつ空配列でない時だけ描画（スラグ対応版）
        if (
          dateAssignments[key] &&
          Array.isArray(dateAssignments[key]) &&
          dateAssignments[key].length > 0
        ) {
          const assignedSlugs = dateAssignments[key];

          // 1. 割り当てられたスラグを元に、カテゴリ情報を取得し配列化
          let assignedCategories = assignedSlugs // ★ letに変更
            .map(slug => userCategories.find(c => c.slug === slug))
            .filter(cat => cat) // 見つからないカテゴリは除外
            .filter(cat => cat.visible !== false);

          //【絞り込み処理】表示が許可されたカテゴリのリストを取得
          const visibleCategorySlugs = window.tintcalPluginData?.visibleCategories;

          // リストが存在し、配列である場合のみ絞り込みを実行
          if (Array.isArray(visibleCategorySlugs)) {
            assignedCategories = assignedCategories.filter(cat => {
              return visibleCategorySlugs.includes(cat.slug);
            });
          }

          // 2. 取得したカテゴリを order 順（昇順）でソート
          assignedCategories.sort((a, b) => (a.order || 0) - (b.order || 0));

          // 3. 最も優先度の高い（=orderが最小の）カテゴリの色と名前を適用
          if (assignedCategories.length > 0) {
            const highPriorityCat = assignedCategories[0]; // ソート後、最初の要素が最高優先度
            cell.style.backgroundColor = highPriorityCat.color;
            if (titleText) titleText += " / ";
            titleText += highPriorityCat.name;
          }
        } else if (
          dateAssignments[key] && !Array.isArray(dateAssignments[key])
        ) {
          const assignmentValue = dateAssignments[key];
          const assignmentSlug =
            typeof assignmentValue === "number" || /^\d+$/.test(assignmentValue)
              ? ((userCategories.find(c => c.id === Number(assignmentValue)) || {}).slug || "")
              : assignmentValue;
          const cat = userCategories.find(c => c.slug === assignmentSlug);
          if (cat) {
            cell.style.backgroundColor = cat.color;
            if (titleText) titleText += " / ";
            titleText += cat.name;
          }
        }

        if (titleText) {
          cell.title = titleText;
        }

        // 個別編集画面のプレビューか、WordPressのプレビュー画面ではクリックを無効にする
        const previewWrapper = document.getElementById('tintcal-individual-preview');
        const isWpPreview = window.tintcalPluginData?.isPreview === "1" || window.tintcalPluginData?.isPreview === "true";

        // 管理画面のプレビューでもなく、フロントのプレビュー画面でもない場合のみクリック可能にする
        if (!previewWrapper && !isWpPreview) {
          cell.addEventListener("click", () => {
            // カテゴリが登録されているかチェック
            const categories = window.tintcalPluginData?.categories || [];
            if (categories.length === 0) {
              // カテゴリがなければメッセージを表示して処理を中断
              showTemporaryMessage("⚠️ " + I18N.pleaseCreateCategory, 3000);
              return;
            }
            // 既存のポップアップがあれば削除する
            const existingModal = document.querySelector(".tintcal-assign-modal");
            if (existingModal) {
              existingModal.remove();
            }
            // 単一選択UI（ラジオ）: スラグ対応（Single Category Product）
            const categoryList = document.createElement("div");
            const assigned = Array.isArray(dateAssignments[key]) ? dateAssignments[key] : (dateAssignments[key] ? [dateAssignments[key]] : []);
            const preselect = assigned.length ? assigned[0] : null;
            userCategories.forEach(cat => {
              const label = document.createElement("label");
              label.style.display = "block";
              const radio = document.createElement("input");
              radio.type = "radio";
              radio.name = "tintcal-assign-one";
              radio.value = cat.slug;
              if (preselect === cat.slug) radio.checked = true;
              label.appendChild(radio);
              label.appendChild(document.createTextNode(cat.name));
              categoryList.appendChild(label);
            });

            const modal = document.createElement("div");
            modal.className = "tintcal-assign-modal";
            modal.style.position = "absolute";
            modal.style.zIndex = 1000;

            modal.appendChild(categoryList);

            // ボタンをまとめるための箱(div)を作成
            const buttonWrapper = document.createElement("div");
            // CSSで定義したスタイルを適用するためのクラス名を追加
            buttonWrapper.className = "button-wrapper";

            // 保存ボタンの作成（青いプライマリボタン）
            const saveBtn = document.createElement("button");
            saveBtn.className = "button button-primary";
            saveBtn.textContent = "保存";
            buttonWrapper.appendChild(saveBtn);

            // キャンセルボタンの作成（白いセカンダリボタン）
            const cancelBtn = document.createElement("button");
            cancelBtn.type = "button";
            cancelBtn.className = "button button-secondary";
            cancelBtn.textContent = "キャンセル";
            buttonWrapper.appendChild(cancelBtn);

            // 作成したボタンの箱をポップアップに追加
            modal.appendChild(buttonWrapper);
            
            document.body.appendChild(modal);

            const rect = cell.getBoundingClientRect();
            modal.style.left = `${rect.left + window.scrollX}px`;
            modal.style.top = `${rect.bottom + window.scrollY}px`;

            // --- ポップアップを閉じるためのイベントリスナーを設定 ---

            // キーボードのEscapeキーで閉じる処理の定義
            const handleKeyDown = (event) => {
              if (event.key === 'Escape') {
                closeModal();
              }
            };

            // ポップアップの外側をクリックした時に閉じる処理の定義
            const handleClickOutside = (event) => {
              // クリックされた場所がポップアップの外側、かつ、元のセルでもない場合
              if (!modal.contains(event.target) && event.target !== cell) {
                closeModal();
              }
            };

            // ★重要★ ポップアップを閉じて、不要なイベントリスナーを解除する「お片付け関数」
            const closeModal = () => {
              modal.remove();
              document.removeEventListener('keydown', handleKeyDown);
              document.removeEventListener('click', handleClickOutside);
            };

            // ページ全体を監視するイベントリスナーを登録
            document.addEventListener('keydown', handleKeyDown);
            document.addEventListener('click', handleClickOutside, true);

            // キャンセルボタンのクリックイベント
            cancelBtn.addEventListener('click', closeModal);

            // 保存ボタンの処理
            saveBtn.addEventListener("click", async function() {
              saveBtn.disabled = true;
              saveBtn.textContent = '保存中...';

              try {
                const selected = categoryList.querySelector("input[type=radio]:checked");
                const checkedSlugs = selected ? [selected.value] : [];

                // シンプルなAjaxリクエストを直接実行
                const response = await fetch(tintcalPluginData.ajaxUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({
                        action: "save_tintcal_assignment", // 1日分を保存するアクション
                        date: key, // key はポップアップ表示時に取得した日付
                        categories: JSON.stringify(checkedSlugs),
                        _ajax_nonce: tintcalPluginData.nonce,
                    }),
                });
                const result = await response.json();

                if (result.success && result.data) {

                    
                    window.tintcalPluginData.categories = result.data.categories;
                                        
                    window.tintcalPluginData.assignments = result.data.assignments;

                    // 最新のデータでUI全体を再描画
                    if (typeof window.drawCalendar === 'function') {
                        await window.drawCalendar();
                    }
                    if (typeof window.renderCategoryTable === 'function') {
                        window.renderCategoryTable();
                    }

                    showTemporaryMessage("✅ " + I18N.saved);
                    closeModal();

                } else {
                    alert(result.data?.message || I18N.failedToSave);
                    saveBtn.disabled = false;
                    saveBtn.textContent = '保存';
                }
              } catch (error) {
                  console.error("保存失敗（通信エラー）：", error);
                  alert(I18N.failedToSave);
                  saveBtn.disabled = false;
                  saveBtn.textContent = '保存';
              }
            });
          });
        }

        date++;
      }
      row.appendChild(cell);
    }
    calendarBody.appendChild(row);
  }
  renderLegend?.();
}

function renderLegend() {
  const container = document.querySelector(".calendar-legend");
  if (!container || !window.tintcalPluginData?.categories) return;

  const categories = window.tintcalPluginData.categories;
  const isVisible = tintcalPluginData?.showLegend == 1;

  if (!isVisible) {
    container.style.display = "none";
    return;
  } else {
    container.style.display = "block";
  }

  container.innerHTML = ""; // クリアしてから描画

  // ▼▼▼ 変更点：「表示」がONのカテゴリだけに絞り込む ▼▼▼
  const visibleCategories = categories.filter(cat => cat.visible !== false);

  visibleCategories.forEach(cat => { // ← ループする配列を visibleCategories に変更
    const item = document.createElement("div");
    item.style.display = "inline-block";
    item.style.marginRight = "10px";
    item.innerHTML = `<span style="display:inline-block;width:12px;height:12px;background:${cat.color};margin-right:4px;border:1px solid #ccc;"></span>${cat.name}`;
    container.appendChild(item);
  });
}

function attachMonthNavigation() {
    const calendarContainerInner = document.querySelector(".tintcal-calendar-container-inner");
    if (!calendarContainerInner) return;

    // 前月ボタンクリック（要素があれば）
    var prevBtn = calendarContainerInner.querySelector(".prev-month");
    // 修正後の prevBtn
    if (prevBtn) {
      prevBtn.addEventListener("click", async () => {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        await drawCalendar();
      });
    }

    // 翌月ボタンクリック（要素があれば）
    var nextBtn = calendarContainerInner.querySelector(".next-month");
    // 修正後の nextBtn
    if (nextBtn) {
      nextBtn.addEventListener("click", async () => {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        await drawCalendar(); // シンプルに描画関数を呼ぶだけ
      });
    }

    var todayBtn = calendarContainerInner.querySelector(".back-to-today");
    // 修正後の todayBtn
    if (todayBtn) {
        todayBtn.addEventListener("click", async () => {
            const today = new Date();
            currentYear = today.getFullYear();
            currentMonth = today.getMonth();
            await drawCalendar(); // シンプルに描画関数を呼ぶだけ
        });
    }
}


// =============================
// 初期化処理
// =============================
window.addEventListener("DOMContentLoaded", () => {
  // 変数をここで初期化
  const today = new Date();
  currentYear = today.getFullYear();
  currentMonth = today.getMonth();

    // ── URLパラメータに「import=success」または「import=error」があればポップアップ
    (function() {
      const params = new URLSearchParams(window.location.search);
      const importStatus = params.get("import");
      if (importStatus === "success") {
        // インポート成功時のメッセージ
        if (typeof showTemporaryMessage === "function") {
          showTemporaryMessage("✅ " + I18N.importSuccess);
        }
      } else if (importStatus === "error") {
        // インポート失敗時のメッセージ
        if (typeof showTemporaryMessage === "function") {
          showTemporaryMessage("⚠️ " + I18N.importError);
        }
      }
    })();

  const enableHolidays = document.getElementById("tintcal-enable-holidays")?.value === "1";
  const legendToggle = document.getElementById("toggle-legend");
  if (legendToggle) {
    legendToggle.addEventListener("change", renderLegend);
  }
  renderLegend(); // 初回表示

  const yearsToFetch = [currentYear - 1, currentYear, currentYear + 1];
  
  const fileInput = document.getElementById("importCalendarFile");
  const importButton = document.getElementById("importCalendarBtn");

  if (fileInput && importButton) {
    importButton.addEventListener("click", () => {
      if (fileInput.files.length === 0) {
        alert(I18N.pleaseSelectFile);
        return;
      }

      const reader = new FileReader();
      reader.onload = function(e) {
        const content = e.target.result;
        if (typeof handleImportJSON === "function") {
          // チェックボックスの checked 状態を読んで mergeMode を渡す
          const appendMode = document.querySelector('input[name="import_append_mode"]').checked;
          handleImportJSON(content, appendMode); // true: 追加モード, false: 上書きモード
        } else {
          console.error("❌ handleImportJSON が未定義です");
        }
      };
      reader.readAsText(fileInput.files[0]);
    });
  }
});

// =============================
// 各種ボタン・リンクのイベント処理
// =============================
document.addEventListener("DOMContentLoaded", function () {
  
  const resetBtn = document.getElementById("reset-assignments");
  if (resetBtn) {
    // async を追加
    resetBtn.addEventListener("click", async () => {
      if (!confirm(I18N.resetDatesConfirm)) return;

      try {
        const response = await fetch(tintcalPluginData.ajaxUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded"
          },
          body: new URLSearchParams({
            action: "reset_tintcal_assignments",
            _ajax_nonce: tintcalPluginData.nonce
          })
        });

        const json = await response.json();

        // サーバーでのリセットが成功した場合のみ、画面を更新
        if (json.success) {
          window.tintcalPluginData.assignments = {}; // JavaScript上のデータも空にする
          drawCalendar(); // 空になったデータでカレンダーを再描画
          alert(I18N.resetDatesOk);
        } else {
          alert(I18N.resetFailed);
        }
      } catch (err) {
        console.error("❌ 初期化エラー:", err);
        alert(I18N.resetError);
      }
    });
  }

  const resetAllBtn = document.getElementById("reset-all-data");
  if (resetAllBtn) {
    // async を追加
    resetAllBtn.addEventListener("click", async () => {
      if (!confirm(I18N.resetAllConfirm)) return;

      try {
        const response = await fetch(tintcalPluginData.ajaxUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded"
          },
          body: new URLSearchParams({
            action: "reset_tintcal_all",
            _ajax_nonce: tintcalPluginData.nonce
          })
        });

        const json = await response.json();

        // サーバーでのリセットが成功した場合のみ、画面を更新
        if (json.success) {
          window.tintcalPluginData.categories = [];
          window.tintcalPluginData.assignments = {};
          
          // カテゴリリストとカレンダーの両方を再描画
          if (typeof renderCategoryTable === 'function') {
            renderCategoryTable();
          }
          drawCalendar();
          
          alert(I18N.resetAllOk);
        } else {
          alert(I18N.resetFailed);
        }
      } catch (err) {
        console.error("❌ 全データ初期化エラー:", err);
        alert(I18N.resetError);
      }
    });
  }

  const reloadBtn = document.getElementById("reFetchHolidays");
  if (reloadBtn) {
    // async を追加
    reloadBtn.addEventListener("click", async () => {
      // 処理中であることがわかるように、ボタンを一時的に無効化
      reloadBtn.disabled = true;
      reloadBtn.textContent = I18N.loading;

      try {
        const response = await fetch(tintcalPluginData.ajaxUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded"
          },
          body: new URLSearchParams({
            action: "reload_tintcal_holidays",
            _ajax_nonce: tintcalPluginData.nonce
          })
        });
        const json = await response.json();

        if (json.success) {
          const results = json.data.results;
          let messages = [ I18N.holidayUpdatedHeader ];

          // 年ごとの結果をチェックしてメッセージを作成
          for (const year in results) {
            if (results[year].success) {
              messages.push(I18N.yearUpdated(year));
            } else {
              messages.push(I18N.yearFailed(year, results[year].error));
            }
          }
          
          // 作成したメッセージを画面に表示（5秒間）
          showTemporaryMessage(messages.join("<br>"), 5000);

          // 1. PHPから受け取った最新の祝日データで、JavaScript上のデータを更新
          window.tintcalPluginData.holidays = json.data.holidays;
          // 2. 新しいデータでカレンダーを再描画
          await drawCalendar();
        } else {
          alert(I18N.fetchHolidayFailed);
        }
      } catch (err) {
        console.error("❌ 祝日再取得エラー:", err);
        alert(I18N.fetchHolidayError);
      } finally {
        // 処理が終わったらボタンを元に戻す
        reloadBtn.disabled = false;
        reloadBtn.textContent = I18N.reloadHoliday;
      }
    });
  }

});

// =============================
// 初期化処理（呼び出し待機用）
// =============================
async function initializeCalendarEditor() {
    const today = new Date();
    currentYear = today.getFullYear();
    currentMonth = today.getMonth();

    await drawCalendar(); // await を追加
    if (typeof window.renderCategoryTable === 'function') {
        window.renderCategoryTable();
    }
    attachMonthNavigation();
}

// 他のJSファイルからこの関数を呼び出せるように、グローバルに公開する
window.initializeCalendarEditor = initializeCalendarEditor;

// ... ファイルの末尾に、drawCalendarをグローバルに公開する処理を追加
window.drawCalendar = drawCalendar;
