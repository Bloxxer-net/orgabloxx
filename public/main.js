const state = {
    user: null,
    projects: [],
    documents: [],
    currentProject: null,
    currentDocument: null,
};

const api = async (action, options = {}) => {
    const { method = 'GET', body, headers } = options;
    const response = await fetch(`api.php?action=${action}${options.query || ''}`, {
        method,
        headers: {
            'Content-Type': 'application/json',
            ...(headers || {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    if (!response.ok) {
        const error = await response.json().catch(() => ({ error: 'Unbekannter Fehler' }));
        throw new Error(error.error || 'Fehler');
    }
    return response.json();
};

const apiForm = async (action, formData) => {
    const response = await fetch(`api.php?action=${action}`, {
        method: 'POST',
        body: formData,
    });
    if (!response.ok) {
        const error = await response.json().catch(() => ({ error: 'Upload fehlgeschlagen' }));
        throw new Error(error.error || 'Upload fehlgeschlagen');
    }
    return response.json();
};

const byId = (id) => document.getElementById(id);

const authView = byId('auth-view');
const mainView = byId('main-view');
const loginForm = byId('login-form');
const registerForm = byId('register-form');
const loginMessage = byId('login-message');
const registerMessage = byId('register-message');
const tabs = document.querySelectorAll('.tabs button');
const logoutButton = byId('logout');
const projectList = byId('project-list');
const newProjectButton = byId('new-project');
const documentListSection = byId('document-list');
const documentsUl = byId('documents');
const projectTitle = byId('project-title');
const newDocumentButton = byId('new-document');
const editorSection = byId('editor');
const documentTitle = byId('document-title');
const leftColumn = byId('left-column');
const rightColumn = byId('right-column');
const addBlockButton = byId('add-block');
const adminArea = byId('admin-area');
const pendingUsersList = byId('pending-users');
const refreshPendingButton = byId('refresh-pending');
const fileInput = byId('file-input');
const fileList = byId('file-list');

const init = async () => {
    const session = await api('session').catch(() => ({ user: null }));
    if (session.user) {
        state.user = session.user;
        showMain();
        await loadProjects();
        if (state.user.is_admin) {
            adminArea.classList.remove('hidden');
            await loadPendingUsers();
        }
    } else {
        showAuth();
    }
};

const showAuth = () => {
    authView.classList.remove('hidden');
    mainView.classList.add('hidden');
};

const showMain = () => {
    authView.classList.add('hidden');
    mainView.classList.remove('hidden');
};

const renderProjects = () => {
    projectList.innerHTML = '';
    state.projects.forEach((project) => {
        const div = document.createElement('div');
        div.className = 'project' + (state.currentProject && state.currentProject.id === project.id ? ' active' : '');
        div.textContent = project.name;
        div.addEventListener('click', () => selectProject(project));
        projectList.appendChild(div);
    });
};

const loadProjects = async () => {
    const data = await api('projects');
    state.projects = data.projects;
    renderProjects();
};

const selectProject = async (project) => {
    state.currentProject = project;
    renderProjects();
    documentListSection.classList.remove('hidden');
    projectTitle.textContent = project.name;
    const data = await api('documents', { query: `&project_id=${project.id}` });
    state.documents = data.documents;
    renderDocuments();
    editorSection.classList.add('hidden');
};

const renderDocuments = () => {
    documentsUl.innerHTML = '';
    state.documents.forEach((doc) => {
        const li = document.createElement('li');
        li.textContent = doc.title;
        li.addEventListener('click', () => openDocument(doc.id));
        documentsUl.appendChild(li);
    });
};

const openDocument = async (docId) => {
    const data = await api('document', { query: `&id=${docId}` });
    state.currentDocument = data.document;
    documentTitle.textContent = data.document.title;
    editorSection.classList.remove('hidden');
    renderBlocks();
    await loadFiles();
};

const createProject = async () => {
    const name = prompt('Projektname');
    if (!name) return;
    const description = prompt('Beschreibung (optional)') || '';
    const data = await api('projects', { method: 'POST', body: { name, description } });
    state.projects.unshift(data.project);
    renderProjects();
};

const createDocument = async () => {
    if (!state.currentProject) return;
    const title = prompt('Dokumenttitel');
    if (!title) return;
    const data = await api('documents', { method: 'POST', body: { project_id: state.currentProject.id, title } });
    state.documents.unshift(data.document);
    renderDocuments();
    await openDocument(data.document.id);
};

const renderBlocks = () => {
    leftColumn.innerHTML = '';
    rightColumn.innerHTML = '';
    state.currentDocument.blocks.forEach((block) => {
        leftColumn.appendChild(createBlockElement(block, 'left'));
        rightColumn.appendChild(createBlockElement(block, 'right'));
    });
};

const createBlockElement = (block, side) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'text-block';
    wrapper.dataset.blockId = block.id;

    const textarea = document.createElement('textarea');
    textarea.value = side === 'left' ? block.left_text : block.right_summary;
    textarea.placeholder = side === 'left' ? 'Freitext eingeben…' : 'Stichpunkte eingeben…';
    textarea.addEventListener('change', () => saveBlock(block.id, side, textarea.value));
    textarea.addEventListener('blur', () => saveBlock(block.id, side, textarea.value));
    wrapper.appendChild(textarea);

    if (side === 'right' && block.suggestions) {
        block.suggestions.forEach((suggestion) => {
            const suggestionDiv = document.createElement('div');
            suggestionDiv.className = 'suggestion';
            const title = document.createElement('h4');
            title.textContent = 'Änderungsvorschlag';
            const text = document.createElement('p');
            text.textContent = suggestion.suggestion_text;
            const applyBtn = document.createElement('button');
            applyBtn.textContent = 'Übernehmen';
            applyBtn.addEventListener('click', () => applySuggestion(block.id, suggestion.id));
            const deleteBtn = document.createElement('button');
            deleteBtn.textContent = 'Löschen';
            deleteBtn.className = 'secondary';
            deleteBtn.addEventListener('click', () => deleteSuggestion(suggestion.id));
            suggestionDiv.appendChild(title);
            suggestionDiv.appendChild(text);
            suggestionDiv.appendChild(applyBtn);
            suggestionDiv.appendChild(deleteBtn);
            wrapper.appendChild(suggestionDiv);
        });
    }

    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = 'Abschnitt löschen';
    deleteBtn.className = 'secondary';
    deleteBtn.addEventListener('click', () => deleteBlock(block.id));
    wrapper.appendChild(deleteBtn);

    return wrapper;
};

const saveBlock = async (blockId, side, value) => {
    try {
        const payload = side === 'left' ? { type: 'left', text: value } : { type: 'right', summary: value };
        const data = await api('block', { method: 'PUT', query: `&id=${blockId}`, body: payload });
        const index = state.currentDocument.blocks.findIndex((b) => b.id === blockId);
        if (index !== -1) {
            state.currentDocument.blocks[index] = data.block;
        }
        renderBlocks();
    } catch (error) {
        console.error(error);
    }
};

const addBlock = async () => {
    if (!state.currentDocument) return;
    const position = state.currentDocument.blocks.length + 1;
    const data = await api('block', { method: 'POST', body: { document_id: state.currentDocument.id, position } });
    state.currentDocument.blocks.push(data.block);
    renderBlocks();
};

const deleteBlock = async (blockId) => {
    if (!confirm('Abschnitt wirklich löschen?')) return;
    await api('block', { method: 'DELETE', query: `&id=${blockId}` });
    state.currentDocument.blocks = state.currentDocument.blocks.filter((b) => b.id !== blockId);
    renderBlocks();
};

const applySuggestion = async (blockId, suggestionId) => {
    const data = await api('suggestion', { method: 'POST', body: { id: blockId, suggestion_id: suggestionId } });
    const index = state.currentDocument.blocks.findIndex((b) => b.id === blockId);
    if (index !== -1) {
        state.currentDocument.blocks[index] = data.block;
    }
    renderBlocks();
};

const deleteSuggestion = async (suggestionId) => {
    await api('suggestion', { method: 'DELETE', query: `&id=${suggestionId}` });
    await openDocument(state.currentDocument.id);
};

const loadPendingUsers = async () => {
    const data = await api('pendingUsers');
    pendingUsersList.innerHTML = '';
    data.users.forEach((user) => {
        const li = document.createElement('li');
        li.textContent = `${user.username} (${user.created_at})`;
        const btn = document.createElement('button');
        btn.textContent = 'Freischalten';
        btn.addEventListener('click', async () => {
            await api('approveUser', { method: 'POST', body: { user_id: user.id } });
            await loadPendingUsers();
        });
        li.appendChild(btn);
        pendingUsersList.appendChild(li);
    });
};

const loadFiles = async () => {
    if (!state.currentProject || !state.currentDocument) return;
    const data = await api('files', { query: `&project_id=${state.currentProject.id}&document_id=${state.currentDocument.id}` });
    fileList.innerHTML = '';
    data.files.forEach((file) => {
        const li = document.createElement('li');
        const link = document.createElement('a');
        link.href = `file.php?id=${file.id}`;
        link.target = '_blank';
        link.textContent = file.filename;
        li.appendChild(link);
        fileList.appendChild(li);
    });
};

const uploadFile = async (event) => {
    if (!event.target.files || !state.currentProject) return;
    const file = event.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    formData.append('project_id', state.currentProject.id);
    if (state.currentDocument) {
        formData.append('document_id', state.currentDocument.id);
    }
    await apiForm('files', formData);
    await loadFiles();
    fileInput.value = '';
};

loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(loginForm);
    try {
        const data = await api('login', {
            method: 'POST',
            body: {
                username: formData.get('username'),
                password: formData.get('password'),
            },
        });
        state.user = data.user;
        showMain();
        await loadProjects();
        if (state.user.is_admin) {
            adminArea.classList.remove('hidden');
            await loadPendingUsers();
        }
    } catch (error) {
        loginMessage.textContent = error.message;
    }
});

registerForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(registerForm);
    try {
        const data = await api('register', {
            method: 'POST',
            body: {
                username: formData.get('username'),
                password: formData.get('password'),
            },
        });
        registerMessage.textContent = data.message;
        registerForm.reset();
    } catch (error) {
        registerMessage.textContent = error.message;
    }
});

logoutButton.addEventListener('click', async () => {
    await api('logout');
    state.user = null;
    state.projects = [];
    state.currentProject = null;
    state.documents = [];
    state.currentDocument = null;
    showAuth();
});

newProjectButton.addEventListener('click', createProject);
newDocumentButton.addEventListener('click', createDocument);
addBlockButton.addEventListener('click', addBlock);
fileInput.addEventListener('change', uploadFile);
refreshPendingButton.addEventListener('click', loadPendingUsers);

tabs.forEach((button) => {
    button.addEventListener('click', () => {
        tabs.forEach((btn) => btn.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach((pane) => pane.classList.remove('active'));
        button.classList.add('active');
        byId(button.dataset.tab).classList.add('active');
    });
});

init();
