import sys
import re

with open('mobile/lib/screens/chat/chat_room_screen.dart', 'r', encoding='utf-8') as f:
    code = f.read()

# I want to replace the `Flexible` that contains the chat bubble with a more modern design with tail, 
# and make sure the Avatar is visible.
# Looking at the original code for chat_room_screen.dart, lines 680 to 797 are the Flexible widget.
# Let's replace the whole `Row` that starts at line 629 and ends at 801 (inside _buildMessageBubble).

# We can find the Row inside `return Column` -> `Padding` -> `Row`

start_idx = code.find('                                  child: Row(')
end_idx = code.find('                                  ),\n                                ),\n                              ],\n                            );', start_idx) + 38

# This is tricky because there are multiple Rows. Let's use regex to find the Row that builds the chat message.
# Wait, actually I can just use replace directly for the Bubble decoration.
# Current decoration:
'''
                                            decoration: BoxDecoration(
                                              color: isMe
                                                  ? AppTheme.primary
                                                  : Colors.white,
                                              borderRadius: BorderRadius.only(
                                                topLeft: const Radius.circular(16),
                                                topRight: const Radius.circular(16),
                                                bottomLeft: Radius.circular(
                                                  isMe || isSameSenderAsPrevious ? 16 : 4,
                                                ),
                                                bottomRight: Radius.circular(
                                                  !isMe || isSameSenderAsPrevious ? 16 : 4,
                                                ),
                                              ),
                                              boxShadow: [
                                                BoxShadow(
                                                  color: Colors.black.withOpacity(0.04),
                                                  spreadRadius: 0,
                                                  blurRadius: 4,
                                                  offset: const Offset(0, 2),
                                                ),
                                              ],
                                            ),
'''

new_decoration = '''
                                            decoration: BoxDecoration(
                                              color: isMe
                                                  ? AppTheme.primary
                                                  : Colors.white,
                                              borderRadius: BorderRadius.only(
                                                topLeft: const Radius.circular(18),
                                                topRight: const Radius.circular(18),
                                                bottomLeft: Radius.circular(isMe || isSameSenderAsPrevious ? 18 : 0),
                                                bottomRight: Radius.circular(!isMe || isSameSenderAsPrevious ? 18 : 0),
                                              ),
                                              boxShadow: [
                                                BoxShadow(
                                                  color: Colors.black.withOpacity(0.06),
                                                  spreadRadius: 0,
                                                  blurRadius: 6,
                                                  offset: const Offset(0, 2),
                                                ),
                                              ],
                                            ),
'''

code = code.replace('''                                            decoration: BoxDecoration(
                                              color: isMe
                                                  ? AppTheme.primary
                                                  : Colors.white,
                                              borderRadius: BorderRadius.only(
                                                topLeft: const Radius.circular(16),
                                                topRight: const Radius.circular(16),
                                                bottomLeft: Radius.circular(
                                                  isMe || isSameSenderAsPrevious ? 16 : 4,
                                                ),
                                                bottomRight: Radius.circular(
                                                  !isMe || isSameSenderAsPrevious ? 16 : 4,
                                                ),
                                              ),
                                              boxShadow: [
                                                BoxShadow(
                                                  color: Colors.black.withOpacity(0.04),
                                                  spreadRadius: 0,
                                                  blurRadius: 4,
                                                  offset: const Offset(0, 2),
                                                ),
                                              ],
                                            ),''', new_decoration)

# Replace the input field container to look more modern
# Current input field decoration:
'''
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(24),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withOpacity(0.08),
                                  spreadRadius: 0,
                                  blurRadius: 8,
                                  offset: const Offset(0, 2),
                                ),
                              ],
                            ),
'''

new_input = '''
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(30),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withOpacity(0.1),
                                  spreadRadius: 0,
                                  blurRadius: 10,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
'''

code = code.replace('''                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(24),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withOpacity(0.08),
                                  spreadRadius: 0,
                                  blurRadius: 8,
                                  offset: const Offset(0, 2),
                                ),
                              ],
                            ),''', new_input)


# To make Avatar in groups persist, let's remove `if (isSameSenderAsPrevious) const SizedBox(width: 32) else`
# and replace it with always showing avatar, but hiding the image/letter if isSameSenderAsPrevious so it acts as a placeholder but still retains width
# Currently:
'''
                                      if (!isMe) ...[
                                        if (isSameSenderAsPrevious)
                                          const SizedBox(width: 32)
                                        else
                                          GestureDetector(
'''
avatar_block_old = '''
                                      if (!isMe) ...[
                                        if (isSameSenderAsPrevious)
                                          const SizedBox(width: 32)
                                        else
                                          GestureDetector(
                                            onTap: () => _showUserDetails(
                                              context,
                                              chat.senderId,
                                              chat.namaLengkap ?? 'User',
                                              chat.roleLevel ?? 'Anggota',
                                              photoUrl: chat.senderPhotoUrl,
                                            ),
                                            child: CircleAvatar(
                                              radius: 16,
                                              backgroundColor: _getColorForUser(
                                                chat.senderId,
                                              ),
                                              backgroundImage:
                                                  chat.senderPhotoUrl != null
                                                  ? NetworkImage(
                                                      chat.senderPhotoUrl!,
                                                    )
                                                  : null,
                                              child: chat.senderPhotoUrl == null
                                                  ? Text(
                                                      (chat.namaLengkap !=
                                                                  null &&
                                                              chat
                                                                  .namaLengkap!
                                                                  .isNotEmpty)
                                                          ? chat.namaLengkap![0]
                                                                .toUpperCase()
                                                          : 'U',
                                                      style: const TextStyle(
                                                        color: Colors.white,
                                                        fontSize: 14,
                                                        fontWeight:
                                                            FontWeight.bold,
                                                      ),
                                                    )
                                                  : null,
                                            ),
                                          ),
                                        const SizedBox(width: 8),
                                      ],
'''

avatar_block_new = '''
                                      if (!isMe && widget.type == 'group') ...[
                                        if (isSameSenderAsPrevious)
                                          const SizedBox(width: 32) // Same width as radius 16
                                        else
                                          GestureDetector(
                                            onTap: () => _showUserDetails(
                                              context,
                                              chat.senderId,
                                              chat.namaLengkap ?? 'User',
                                              chat.roleLevel ?? 'Anggota',
                                              photoUrl: chat.senderPhotoUrl,
                                            ),
                                            child: CircleAvatar(
                                              radius: 16,
                                              backgroundColor: _getColorForUser(
                                                chat.senderId,
                                              ),
                                              backgroundImage:
                                                  chat.senderPhotoUrl != null
                                                  ? NetworkImage(
                                                      chat.senderPhotoUrl!,
                                                    )
                                                  : null,
                                              child: chat.senderPhotoUrl == null
                                                  ? Text(
                                                      (chat.namaLengkap !=
                                                                  null &&
                                                              chat
                                                                  .namaLengkap!
                                                                  .isNotEmpty)
                                                          ? chat.namaLengkap![0]
                                                                .toUpperCase()
                                                          : 'U',
                                                      style: const TextStyle(
                                                        color: Colors.white,
                                                        fontSize: 14,
                                                        fontWeight:
                                                            FontWeight.bold,
                                                      ),
                                                    )
                                                  : null,
                                            ),
                                          ),
                                        const SizedBox(width: 8),
                                      ],
'''
code = code.replace(avatar_block_old, avatar_block_new)

with open('mobile/lib/screens/chat/chat_room_screen.dart', 'w', encoding='utf-8') as f:
    f.write(code)

print("Refactored Chat Room Screen.")
