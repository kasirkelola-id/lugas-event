<?php

namespace App\Libraries;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Models\ChatModel;
use App\Models\UserModel;

class ChatServer implements MessageComponentInterface
{
    protected $clients;
    protected $userConnections; // Map user_id to ConnectionInterface

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        echo "Chat Server Started...\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // Store the new connection
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        echo sprintf('Connection %d sending message "%s"' . "\n", $from->resourceId, $msg);
        
        $data = json_decode($msg, true);
        
        // Handle Authentication / Connection Identification
        if (isset($data['action']) && $data['action'] === 'auth') {
            $userId = $data['user_id'] ?? null;
            $karangTarunaId = $data['karang_taruna_id'] ?? null;
            
            if ($userId && $karangTarunaId) {
                // Attach metadata to connection
                $from->userId = $userId;
                $from->karangTarunaId = $karangTarunaId;
                $this->userConnections[$userId] = $from;
                echo "User {$userId} authenticated on connection {$from->resourceId}\n";
            }
            return;
        }

        // Must be authenticated to send messages
        if (!isset($from->userId) || !isset($from->karangTarunaId)) {
            $from->send(json_encode(['error' => 'Unauthenticated connection']));
            return;
        }

        // Handle sending a message
        if (isset($data['action']) && $data['action'] === 'send_message') {
            $type = $data['type'] ?? 'group';
            $message = $data['message'] ?? '';
            $receiverId = $data['receiver_id'] ?? null;

            if (empty($message)) return;

            // Save to database
            $chatModel = new ChatModel();
            $chatData = [
                'karang_taruna_id' => $from->karangTarunaId,
                'type' => $type,
                'sender_id' => $from->userId,
                'receiver_id' => $type === 'private' ? $receiverId : null,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $chatModel->insert($chatData);
            $chatData['id'] = $chatModel->getInsertID();

            // Enrich with sender details
            $userModel = new UserModel();
            $sender = $userModel->find($from->userId);
            $chatData['nama_lengkap'] = $sender['nama_lengkap'] ?? 'Unknown';
            $chatData['role_level'] = $sender['role_level'] ?? 'anggota';

            $payload = json_encode([
                'action' => 'new_message',
                'data' => $chatData
            ]);

            // Broadcast logic
            if ($type === 'group') {
                // Send to everyone in the same Karang Taruna
                foreach ($this->clients as $client) {
                    if (isset($client->karangTarunaId) && $client->karangTarunaId == $from->karangTarunaId) {
                        $client->send($payload);
                    }
                }
            } elseif ($type === 'private') {
                // Send to sender
                $from->send($payload);
                // Send to receiver if online
                if (isset($this->userConnections[$receiverId])) {
                    $receiverConn = $this->userConnections[$receiverId];
                    if ($receiverConn->karangTarunaId == $from->karangTarunaId) {
                        $receiverConn->send($payload);
                    }
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        if (isset($conn->userId)) {
            unset($this->userConnections[$conn->userId]);
        }
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}
