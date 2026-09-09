import sys
import re

with open('mobile/lib/screens/pengelola/pengelola_pengguna_screen.dart', 'r', encoding='utf-8') as f:
    pengelola_code = f.read()

with open('mobile/lib/screens/admin/admin_pengguna_screen.dart', 'r', encoding='utf-8') as f:
    admin_code = f.read()

# Extract _showUserForm from admin
match = re.search(r'Future<void> _showUserForm.*?^\s*\}\s*$', admin_code, re.DOTALL | re.MULTILINE)
show_user_form = match.group(0) if match else ''

# Replace class names
new_code = pengelola_code.replace('PengelolaPenggunaScreen', 'AdminPenggunaScreen')
new_code = new_code.replace('_PengelolaPenggunaScreenState', '_AdminPenggunaScreenState')
new_code = new_code.replace('Kelola Anggota', 'Kelola Pengguna Admin')

# Insert _showUserForm, _formKey, and controllers into AdminPenggunaScreenState
state_class_start = new_code.find('class _AdminPenggunaScreenState')
state_class_body_start = new_code.find('{', state_class_start) + 1

controllers_code = '''
  final _formKey = GlobalKey<FormState>();
  final _namaLengkapController = TextEditingController();
  final _namaPanggilanController = TextEditingController();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _whatsappController = TextEditingController();
  final _rtController = TextEditingController();
  String _selectedRole = 'pengelola';
  
  // Callback to trigger rebuild when new user added
  int _refreshCounter = 0;
  
  void _triggerRefresh() {
    setState(() {
      _refreshCounter++;
    });
  }

'''

new_code = new_code[:state_class_body_start] + controllers_code + new_code[state_class_body_start:]

# Inject _showUserForm method before build() inside _AdminPenggunaScreenState
build_idx = new_code.find('Widget build(BuildContext context) {')

# The original show_user_form calls _loadData(). We need to change it to _triggerRefresh()
show_user_form = show_user_form.replace('_loadData();', '_triggerRefresh();')
# Also handle _showSnackbar
show_user_form = show_user_form.replace('_showSnackbar(', 'FeedbackDialogs.showSnackbar(context, ')
show_user_form = show_user_form.replace('setStateDialog(', 'setStateDialog(')

new_code = new_code[:build_idx] + show_user_form + '\n\n  @override\n  ' + new_code[build_idx:]

# Inject FloatingActionButton in Scaffold
scaffold_idx = new_code.find('body: TabBarView(')
if scaffold_idx != -1:
    bg_idx = new_code.find('backgroundColor: AppTheme.background,')
    fab_code = '''
        floatingActionButton: FloatingActionButton.extended(
          onPressed: () => _showUserForm(),
          backgroundColor: AppTheme.primary,
          icon: const Icon(Icons.person_add_outlined, color: Colors.white),
          label: const Text('Tambah', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
        ),
'''
    new_code = new_code[:bg_idx] + fab_code + '        ' + new_code[bg_idx:]

# Also we need to pass a callback to _UserListTab to trigger edit form.
tab_view_code = '''
          body: TabBarView(
            key: ValueKey(_refreshCounter),
            children: [
              _UserListTab(
                isActiveTab: true,
                searchQuery: _searchQuery,
                rtFilter: _rtFilter,
                currentUser: _currentUser,
                onEditUser: (user) => _showUserForm(user: user),
              ),
              _UserListTab(
                isActiveTab: false,
                searchQuery: _searchQuery,
                rtFilter: _rtFilter,
                currentUser: _currentUser,
                onEditUser: (user) => _showUserForm(user: user),
              ),
            ],
          ),
'''
new_code = re.sub(r'body: TabBarView\(.*?\),', tab_view_code, new_code, flags=re.DOTALL)

# Add onEditUser to _UserListTab
user_list_tab_start = new_code.find('class _UserListTab extends StatefulWidget {')
user_list_tab_end = new_code.find('class _UserListTabState', user_list_tab_start)
user_list_tab_code = new_code[user_list_tab_start:user_list_tab_end]

user_list_tab_code = user_list_tab_code.replace(
    'final UserModel? currentUser;',
    'final UserModel? currentUser;\n  final Function(UserModel) onEditUser;'
)
user_list_tab_code = user_list_tab_code.replace(
    'required this.currentUser,',
    'required this.currentUser,\n    required this.onEditUser,'
)
new_code = new_code[:user_list_tab_start] + user_list_tab_code + new_code[user_list_tab_end:]

# Inject Edit button into _UserListTabState's action buttons
action_button_idx = new_code.find('Row(\n                mainAxisAlignment: MainAxisAlignment.spaceEvenly,\n                children: [')
if action_button_idx != -1:
    edit_btn_code = '''
                  if (widget.currentUser?.roleLevel == 'admin')
                    _buildActionButton(
                      Icons.edit_outlined,
                      'Edit',
                      AppTheme.info,
                      () => widget.onEditUser(user),
                    ),
'''
    children_idx = new_code.find('children: [', action_button_idx) + 11
    new_code = new_code[:children_idx] + edit_btn_code + new_code[children_idx:]

# And admin user needs to see all actions if they are 'admin'. 
new_code = new_code.replace("widget.currentUser?.roleLevel == 'ketua'", "(widget.currentUser?.roleLevel == 'ketua' || widget.currentUser?.roleLevel == 'admin')")

# Also add imports needed for forms
imports_idx = new_code.find('import \'../../models/user_model.dart\';')
extra_imports = '''
import '../widgets/common/custom_button.dart';
import '../widgets/common/custom_text_field.dart';
'''
new_code = new_code[:imports_idx] + extra_imports + new_code[imports_idx:]

with open('mobile/lib/screens/admin/admin_pengguna_screen.dart', 'w', encoding='utf-8') as f:
    f.write(new_code)
print('Rewrite successful!')
