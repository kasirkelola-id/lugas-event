class Inventory {
  final int id;
  final int karangTarunaId;
  final String name;
  final int totalQuantity;
  final int availableQuantity;
  final String? condition;

  Inventory({
    required this.id,
    required this.karangTarunaId,
    required this.name,
    required this.totalQuantity,
    required this.availableQuantity,
    this.condition,
  });

  factory Inventory.fromJson(Map<String, dynamic> json) {
    return Inventory(
      id: int.parse(json['id'].toString()),
      karangTarunaId: int.parse(json['karang_taruna_id'].toString()),
      name: json['name'],
      totalQuantity: int.parse(json['total_quantity'].toString()),
      availableQuantity: int.parse(json['available_quantity'].toString()),
      condition: json['condition'],
    );
  }
}
