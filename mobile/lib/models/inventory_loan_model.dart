class InventoryLoan {
  final int id;
  final int inventoryId;
  final int userId;
  final int quantity;
  final DateTime borrowDate;
  final DateTime returnDate;
  final String status; // pending, approved, returned, rejected
  final String? inventoryName;
  final String? userName;

  InventoryLoan({
    required this.id,
    required this.inventoryId,
    required this.userId,
    required this.quantity,
    required this.borrowDate,
    required this.returnDate,
    required this.status,
    this.inventoryName,
    this.userName,
  });

  factory InventoryLoan.fromJson(Map<String, dynamic> json) {
    return InventoryLoan(
      id: int.parse(json['id'].toString()),
      inventoryId: int.parse(json['inventory_id'].toString()),
      userId: int.parse(json['user_id'].toString()),
      quantity: int.parse(json['quantity'].toString()),
      borrowDate: DateTime.parse(json['borrow_date']),
      returnDate: DateTime.parse(json['return_date']),
      status: json['status'] ?? 'pending',
      inventoryName: json['inventory_name'],
      userName: json['user_name'],
    );
  }
}
