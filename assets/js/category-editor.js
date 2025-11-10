// category-editor.js - 管理画面用 カテゴリ編集ロジック

(function(I18N) {
  // i18n defaults (override via wp_localize_script -> window.tintcalI18n)
  Object.assign(I18N, (window.tintcalI18n || {
    tableHeaders: { name: 'カテゴリ名', color: '色コード', visible: '表示', actions: '操作' },
    edit: '編集',
    save: '保存',
    delete: '削除',
    confirmDelete: (name) => `カテゴリ「${name || ''}」を削除してもよろしいですか？\nこの操作は取り消せません。`,
    emptyName: 'カテゴリ名は空にできません',
    duplicateName: 'すでに同じ名前のカテゴリが存在します。',
    saveFailed: '保存に失敗しました',
    networkError: '通信エラーが発生しました'
  }));

  // =============================
  // 初期化・イベントバインド
  // =============================
  document.addEventListener("DOMContentLoaded", function () {
    // DOMの準備ができたら、カテゴリ追加ボタンなどの基本的なUIイベントだけを先に設定
    initCategoryEditorUI();
  });

  // =============================
  // UUID v4 生成（簡易バージョン）
  // =============================
  function generateUUIDv4() {
    return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
      (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
    );
  }

  // =============================
  // メインのUI更新関数（司令塔）
  // =============================
  function updateUI() {
    // この関数が呼ばれたら、カテゴリテーブルとカレンダーの両方を再描画する
    if (typeof window.renderCategoryTable === 'function') {
      window.renderCategoryTable();
    }
    if (typeof window.drawCalendar === 'function') {
      window.drawCalendar();
    }
    // カテゴリ数に応じて追加フォームを制御
    updateAddCategoryFormState();
  }
  window.updateUI = updateUI; // グローバルに公開

  // =============================
  // カテゴリ追加フォームの有効/無効制御
  // =============================
  function updateAddCategoryFormState() {
    const categories = loadCategories();
    const nameInput = document.querySelector("#new-category-name");
    const colorInput = document.querySelector("#new-category-color");
    const addBtn = document.querySelector("#add-category");

    if (!nameInput || !colorInput || !addBtn) return;

    if (categories.length >= 1) {
      // 既に1件ある場合：フォームを無効化
      nameInput.disabled = true;
      colorInput.disabled = true;
      addBtn.disabled = true;
      nameInput.placeholder = "無料版は1カテゴリのみです";
      nameInput.value = "";

      // メッセージ表示（既存のメッセージがなければ追加）
      let message = document.querySelector("#tintcal-category-limit-message");
      if (!message) {
        message = document.createElement("p");
        message.id = "tintcal-category-limit-message";
        message.style.color = "#666";
        message.style.fontSize = "13px";
        message.style.marginTop = "8px";
        message.textContent = "無料版は1カテゴリのみです。既存カテゴリを編集するか、削除後に新規追加してください。";
        addBtn.parentElement.appendChild(message);
      }
    } else {
      // カテゴリがない場合：フォームを有効化
      nameInput.disabled = false;
      colorInput.disabled = false;
      addBtn.disabled = false;
      nameInput.placeholder = "カテゴリ名（1件のみ）";

      // メッセージを削除
      const message = document.querySelector("#tintcal-category-limit-message");
      if (message) {
        message.remove();
      }
    }
  }

  // =============================
  // カテゴリテーブル描画・UI系関数
  // =============================
  function renderCategoryTable() {
    let categories = Array.isArray(window.tintcalPluginData?.categories)
      ? window.tintcalPluginData.categories
      : Object.values(window.tintcalPluginData.categories || {});

    const categoryTable = document.querySelector("#tintcal-category-table tbody");
    const categoryTableHead = document.querySelector("#tintcal-category-table thead tr");

    if (!categoryTable || !categoryTableHead) return;
    categoryTableHead.innerHTML = `
      <th>${I18N.tableHeaders.name}<\/th>
      <th>${I18N.tableHeaders.color}<\/th>
      <th>${I18N.tableHeaders.visible}<\/th>
      <th>${I18N.tableHeaders.actions}<\/th>
    `;

    categoryTable.innerHTML = "";
    categories
      .sort((a, b) => (a.order || 0) - (b.order || 0))
      .forEach(cat => {
        const row = document.createElement("tr");
        const isVisible = cat.visible !== false;

        row.innerHTML = `
          <td><span class="cat-name">${cat.name}<\/span>
            <input class="edit-name-input" type="text" value="${cat.name}" style="display:none;"><\/td>
          <td><div class="cat-color" style="width: 40px; height: 20px; background:${cat.color || "#000000"}"><\/div>
            <input class="edit-color-input" type="color" value="${cat.color || "#000000"}" style="display:none;"><\/td>
          <td>
            <input type="checkbox" class="toggle-visibility" data-slug="${cat.slug}" ${isVisible ? 'checked' : ''}>
          <\/td>
          <td>
            <button data-slug="${cat.slug}" class="edit-category">${I18N.edit}<\/button>
            <button data-slug="${cat.slug}" class="save-category" style="display:none;">${I18N.save}<\/button>
            <button data-slug="${cat.slug}" class="delete-category">${I18N.delete}<\/button>
          <\/td>
        `;
        categoryTable.appendChild(row);
      });

    bindCategoryEvents();
    updateAddCategoryFormState(); // カテゴリ数に応じてフォーム状態を更新
  }
  window.renderCategoryTable = renderCategoryTable; // グローバルに公開

  // =============================
  // カテゴリ編集イベントバインド
  // =============================
  function bindCategoryEvents() {

    // 削除ボタン
    document.querySelectorAll(".delete-category").forEach(btn => {
      const newBtn = btn.cloneNode(true);
      btn.parentNode.replaceChild(newBtn, btn);
      newBtn.onclick = async function () {
        const slug = this.getAttribute("data-slug");
        const categories = loadCategories();
        const cat = categories.find(c => c.slug === slug);
        const confirmed = confirm(I18N.confirmDelete(cat ? cat.name : ''));
        if (!confirmed) return;
        const updatedCategories = categories.filter(c => c.slug !== slug);
        if (await saveCategories(updatedCategories)) {
          await fetchAllDataFromServer();
          updateUI();
        }
      };
    });

    // 編集ボタン
    document.querySelectorAll(".edit-category").forEach(btn => {
      const newBtn = btn.cloneNode(true);
      btn.parentNode.replaceChild(newBtn, btn);
      newBtn.onclick = function () {
        const row = this.closest("tr");
        document.querySelectorAll(".save-category").forEach(saveBtn => {
          if (saveBtn.style.display !== 'none') {
              saveBtn.dispatchEvent(new Event('cancel-edit'));
          }
        });
        row.querySelector(".cat-name").style.display = "none";
        row.querySelector(".edit-name-input").style.display = "";
        row.querySelector(".cat-color").style.display = "none";
        row.querySelector(".edit-color-input").style.display = "";
        this.style.display = "none";
        row.querySelector(".save-category").style.display = "";
      };
    });

    // 保存ボタン
    document.querySelectorAll(".save-category").forEach(btn => {
      const newBtn = btn.cloneNode(true);
      btn.parentNode.replaceChild(newBtn, btn);
      const saveHandler = async function () {
        const row = newBtn.closest("tr");
        const slug = newBtn.getAttribute("data-slug");
        const newName = row.querySelector(".edit-name-input").value.trim();
        const newColor = row.querySelector(".edit-color-input").value;
        if (!newName) { alert(I18N.emptyName); return; }
        const categories = loadCategories();
        if (categories.some(c => c.slug !== slug && c.name === newName)) { alert(I18N.duplicateName); return; }
        const categoryToUpdate = categories.find(c => c.slug === slug);
        if (categoryToUpdate) {
          categoryToUpdate.name = newName;
          categoryToUpdate.color = newColor;
        }
        if (await saveCategories(categories)) {
          await fetchAllDataFromServer();
          updateUI();
        }
      };
      newBtn.onclick = saveHandler;
      newBtn.addEventListener('cancel-edit', () => {
          const row = newBtn.closest("tr");
          row.querySelector(".cat-name").style.display = "";
          row.querySelector(".edit-name-input").style.display = "none";
          row.querySelector(".cat-color").style.display = "";
          row.querySelector(".edit-color-input").style.display = "none";
          row.querySelector(".edit-category").style.display = "";
          newBtn.style.display = "none";
      });
    });
    
    // 表示/非表示チェックボックス
    document.querySelectorAll(".toggle-visibility").forEach(checkbox => {
      const newCheckbox = checkbox.cloneNode(true);
      checkbox.parentNode.replaceChild(newCheckbox, checkbox);
      newCheckbox.onchange = async function() {
        const slug = this.getAttribute("data-slug");
        const isVisible = this.checked;
        const categories = loadCategories();
        const categoryToUpdate = categories.find(c => c.slug === slug);
        if (categoryToUpdate) {
          categoryToUpdate.visible = isVisible;
          if (await saveCategories(categories)) {
            await fetchAllDataFromServer();
            updateUI();
          }
        }
      };
    });
  }

  // =============================
  // 初期化・データ取得/保存ロジック
  // =============================
  function initCategoryEditorUI() {
    const addCategoryBtn = document.querySelector("#add-category");
    const nameInput = document.querySelector("#new-category-name");
    const colorInput = document.querySelector("#new-category-color");

    if (addCategoryBtn) {
      addCategoryBtn.onclick = async function () {
        let categoriesArr = loadCategories();

        // 無料版：既に1件ある場合は追加不可
        if (categoriesArr.length >= 1) {
          alert("無料版は1カテゴリのみです。既存カテゴリを削除してから新規追加するか、編集ボタンで既存カテゴリを変更してください。");
          return;
        }

        const name = nameInput.value.trim();
        const color = colorInput.value;
        if (!name) { alert("カテゴリ名を入力してください"); return; }

        // 新規カテゴリを作成
        const newCat = {
          id: 1,
          name: name,
          color: color,
          order: 1,
          slug: generateUUIDv4(),
          visible: true
        };
        categoriesArr = [newCat];

        if (await saveCategories(categoriesArr)) {
          await fetchAllDataFromServer();
          updateUI();
          nameInput.value = '';
        }
      };
    }

    // 初期ロード時にもフォーム状態を更新
    updateAddCategoryFormState();
  }

  function loadCategories() {
    const src = window.tintcalPluginData?.categories;
    return JSON.parse(JSON.stringify(Array.isArray(src) ? src : Object.values(src || {})));
  }

  async function saveCategories(data, confirmed = false) { // "confirmed"引数を追加
    try {
      // 単一カテゴリのみ送信するように調整
      if (Array.isArray(data) && data.length > 1) {
        data = [ data[0] ];
      }
      // 送信するデータを準備
      const params = new URLSearchParams({
        action: "save_tintcal_categories",
        categories: encodeURIComponent(JSON.stringify(data)),
        _ajax_nonce: tintcalPluginData.nonce || ""
      });
      
      // 確認済みの場合は、そのフラグも送信データに加える
      if (confirmed) {
        params.append('confirmed', 'true');
      }

      const response = await fetch(tintcalPluginData.ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: params
      });

      const res = await response.json();

      if (res.success && res.data && res.data.notice) {
        // サーバー側で複数をトリムした際の注意を中立表示（Upsellではない）
        alert(res.data.notice || I18N.saveFailed);
      }

      // ▼▼▼ レスポンスのハンドリングを修正 ▼▼▼
      if (res.success) {
        return true; // 保存成功
      } 
      
      // サーバーから「確認が必要」という応答が返ってきた場合
      if (res.data?.confirmation_required) {
        // 確認ダイアログを表示
        if (confirm(res.data.message)) {
          // ユーザーが「OK」を押したら、"confirmed"フラグを付けて再度保存処理を呼び出す
          return await saveCategories(data, true);
        } else {
          // ユーザーが「キャンセル」を押したら、何もせず処理を終了
          return false;
        }
      } else {
        // その他の通常のエラー
        alert(res.data?.message || I18N.saveFailed);
        return false;
      }

    } catch (err) {
      console.error(I18N.networkError + ':', err);
      alert(I18N.networkError);
      return false;
    }
  }

  async function fetchAllDataFromServer() {
    try {
      const actions = ["get_tintcal_categories", "get_tintcal_assignments"];
      const [catRes, assignRes] = await Promise.all(actions.map(action => 
        fetch(tintcalPluginData.ajaxUrl, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({ action, _ajax_nonce: tintcalPluginData.nonce || "" })
        }).then(res => res.json())
      ));

      if (catRes.success) {
        const newCategories = Array.isArray(catRes.data) ? catRes.data : Object.values(catRes.data || {});
        window.tintcalPluginData.categories = newCategories;
        // 最新のカテゴリリストから、表示がONになっているもののslugだけを抽出し、
        // visibleCategoriesをJavaScript側で再生成します。
        window.tintcalPluginData.visibleCategories = newCategories
          .filter(cat => cat.visible !== false)
          .map(cat => cat.slug);
      }
      if (assignRes.success) {
        window.tintcalPluginData.assignments = assignRes.data || {};
      }
      return true;
    } catch (err) {
      console.error("データ取得に失敗しました:", err);
      return false;
    }
  }

  window.fetchAllDataFromServer = fetchAllDataFromServer;
})(window.I18N = window.I18N || {});