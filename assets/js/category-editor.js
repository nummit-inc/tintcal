// category-editor.js - 管理画面用 カテゴリ編集ロジック

// =============================
// 初期化・イベントバインド
// =============================
document.addEventListener("DOMContentLoaded", function () {
  setTimeout(initCategoryEditor, 100); 
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
}
window.updateUI = updateUI; // グローバルに公開

// =============================
// カテゴリテーブル描画・UI系関数
// =============================
function renderCategoryTable() {
  let categories = Array.isArray(window.jcalendarPluginData?.categories)
    ? window.jcalendarPluginData.categories
    : Object.values(window.jcalendarPluginData.categories || {});

  const categoryTable = document.querySelector("#jcalendar-category-table tbody");
  const categoryTableHead = document.querySelector("#jcalendar-category-table thead tr");

  if (!categoryTable || !categoryTableHead) return;

  categoryTableHead.innerHTML = `
    <th>カテゴリ名</th>
    <th>色コード</th>
    <th>表示</th>
    <th>操作</th>
  `;

  categoryTable.innerHTML = "";
  categories
    .sort((a, b) => (a.order || 0) - (b.order || 0))
    .forEach(cat => {
      const row = document.createElement("tr");
      const isVisible = cat.visible !== false;
      const isLicenseValid = window.jcalendarPluginData && window.jcalendarPluginData.isLicenseValid;

      row.innerHTML = `
        <td><span class="cat-name">${cat.name}</span>
          <input class="edit-name-input" type="text" value="${cat.name}" style="display:none;"></td>
        <td><div class="cat-color" style="width: 40px; height: 20px; background:${cat.color || "#000000"}"></div>
          <input class="edit-color-input" type="color" value="${cat.color || "#000000"}" style="display:none;" ${!isLicenseValid ? 'disabled' : ''}></td>
        <td>
          <input type="checkbox" class="toggle-visibility" data-slug="${cat.slug}" ${isVisible ? 'checked' : ''}>
        </td>
        <td>
          <button data-slug="${cat.slug}" class="edit-category">編集</button>
          <button data-slug="${cat.slug}" class="save-category" style="display:none;">保存</button>
          <button data-slug="${cat.slug}" class="delete-category">削除</button>
          <button data-slug="${cat.slug}" class="move-up-category" ${!isLicenseValid ? 'disabled' : ''}>↑</button>
          <button data-slug="${cat.slug}" class="move-down-category" ${!isLicenseValid ? 'disabled' : ''}>↓</button>
        </td>
      `;
      categoryTable.appendChild(row);
    });

  bindCategoryEvents();
}
window.renderCategoryTable = renderCategoryTable; // グローバルに公開

// =============================
// カテゴリ編集イベントバインド
// =============================
function bindCategoryEvents() {

  // 削除ボタン
  document.querySelectorAll(".delete-category").forEach(btn => {
    btn.onclick = async function () {
      const slug = this.getAttribute("data-slug");
      const categories = loadCategories();
      const cat = categories.find(c => c.slug === slug);
      const confirmed = confirm(`カテゴリ「${cat ? cat.name : ''}」を削除してもよろしいですか？\nこの操作は取り消せません。`);
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
    btn.onclick = function () {
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
    const saveHandler = async function () {
      const row = this.closest("tr");
      const slug = this.getAttribute("data-slug");
      const newName = row.querySelector(".edit-name-input").value.trim();
      const newColor = row.querySelector(".edit-color-input").value;
      if (!newName) {
        alert("カテゴリ名は空にできません"); return;
      }
      const categories = loadCategories();
      if (categories.some(c => c.slug !== slug && c.name === newName)) {
        alert("すでに同じ名前のカテゴリが存在します。"); return;
      }
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
    btn.onclick = saveHandler;
    btn.addEventListener('cancel-edit', () => {
        const row = btn.closest("tr");
        row.querySelector(".cat-name").style.display = "";
        row.querySelector(".edit-name-input").style.display = "none";
        row.querySelector(".cat-color").style.display = "";
        row.querySelector(".edit-color-input").style.display = "none";
        row.querySelector(".edit-category").style.display = "";
        btn.style.display = "none";
    });
  });
  
  // 上へ移動ボタン・下へ移動ボタン
  ['.move-up-category', '.move-down-category'].forEach(selector => {
      document.querySelectorAll(selector).forEach(btn => {
        btn.onclick = async function () {
          const slug = this.getAttribute("data-slug");
          let categories = loadCategories();
          const idx = categories.findIndex(c => c.slug === slug);
          const direction = selector.includes('up') ? -1 : 1;
          if ((direction === -1 && idx > 0) || (direction === 1 && idx < categories.length - 1)) {
            [categories[idx].order, categories[idx + direction].order] = [categories[idx + direction].order, categories[idx].order];
            if (await saveCategories(categories)) {
              await fetchAllDataFromServer();
              updateUI();
            }
          }
        };
      });
  });
  
  // 表示/非表示チェックボックス
  document.querySelectorAll(".toggle-visibility").forEach(checkbox => {
    checkbox.onchange = async function() {
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
async function initCategoryEditor() {
  const isLicenseValid = window.jcalendarPluginData && window.jcalendarPluginData.isLicenseValid;

  // ライセンスが有効な時だけ、サーバーから最新データを取得
  if (isLicenseValid) {
    await fetchAllDataFromServer();
  }
  
  updateUI(); 

  const addCategoryBtn = document.querySelector("#add-category");
  const nameInput = document.querySelector("#new-category-name");
  const colorInput = document.querySelector("#new-category-color");

  if (addCategoryBtn) {
    addCategoryBtn.onclick = async function () {
      let categoriesArr = loadCategories();
      // ▼▼▼ ライセンスチェックを、ボタンが押されたこのタイミングで行う ▼▼▼
      if (!isLicenseValid && categoriesArr.length >= 1) {
        alert('複数のカテゴリを作成するには、Pro版ライセンスが必要です。');
        return; // 処理を中断
      }

      const name = nameInput.value.trim();
      const color = colorInput.value;
      if (!name) return alert("カテゴリ名を入力してください");
      
      if (categoriesArr.some(cat => cat.name === name)) {
        alert("すでに同じ名前のカテゴリが存在します。");
        return;
      }
      const maxId = categoriesArr.length > 0 ? Math.max(...categoriesArr.map(c => c.id || 0)) : 0;
      const maxOrder = categoriesArr.length > 0 ? Math.max(...categoriesArr.map(c => c.order || 0)) : 0;
      const newCat = {
        id: maxId + 1, name: name, color: color, order: maxOrder + 1,
        slug: generateUUIDv4(), visible: true
      };
      categoriesArr.push(newCat);
      if (await saveCategories(categoriesArr)) {
        await fetchAllDataFromServer();
        updateUI();
        nameInput.value = '';
      }
    };

    // ライセンス無効の場合、入力欄自体は無効化しない（最初の1つは入力できるようにするため）
    // ただし、既に1つ以上カテゴリがある場合は、ボタンを無効化しておく
    if (!isLicenseValid && loadCategories().length >= 1) {
        addCategoryBtn.disabled = true;
        nameInput.disabled = true;
        colorInput.disabled = true;
    }
  }
}

function loadCategories() {
  const src = window.jcalendarPluginData?.categories;
  return JSON.parse(JSON.stringify(Array.isArray(src) ? src : Object.values(src || {})));
}

async function saveCategories(data, confirmed = false) { // "confirmed"引数を追加
  try {
    // 送信するデータを準備
    const params = new URLSearchParams({
      action: "save_jcalendar_categories",
      categories: encodeURIComponent(JSON.stringify(data)),
      _ajax_nonce: jcalendarPluginData.nonce || ""
    });
    
    // 確認済みの場合は、そのフラグも送信データに加える
    if (confirmed) {
      params.append('confirmed', 'true');
    }

    const response = await fetch(jcalendarPluginData.ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: params
    });

    const res = await response.json();

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
      alert(res.data?.message || "保存に失敗しました");
      return false;
    }

  } catch (err) {
    console.error("通信エラー:", err);
    alert("通信エラーが発生しました");
    return false;
  }
}

async function fetchAllDataFromServer() {
  try {
    const actions = ["get_jcalendar_categories", "get_jcalendar_assignments"];
    const [catRes, assignRes] = await Promise.all(actions.map(action => 
      fetch(jcalendarPluginData.ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ action, _ajax_nonce: jcalendarPluginData.nonce || "" })
      }).then(res => res.json())
    ));

    if (catRes.success) {
      const newCategories = Array.isArray(catRes.data) ? catRes.data : Object.values(catRes.data || {});
      window.jcalendarPluginData.categories = newCategories;
      // 最新のカテゴリリストから、表示がONになっているもののslugだけを抽出し、
      // visibleCategoriesをJavaScript側で再生成します。
      window.jcalendarPluginData.visibleCategories = newCategories
        .filter(cat => cat.visible !== false)
        .map(cat => cat.slug);
    }
    if (assignRes.success) {
      window.jcalendarPluginData.assignments = assignRes.data || {};
    }
    return true;
  } catch (err) {
    console.error("データ取得に失敗しました:", err);
    return false;
  }
}

window.fetchAllDataFromServer = fetchAllDataFromServer;