import glob

def prParser(pr_path):
    with open(pr_path, "r", encoding="utf8") as fr:
        lines = fr.readlines()
        lines = [line.replace("\n", "") for line in lines]
        _, num_vehicles, _, num_depots = int(lines[0].split()[0]), int(lines[0].split()[1]), int(lines[0].split()[2]), int(lines[0].split()[3])
        max_duration_route = int(lines[1].split()[0])
        vehicle_capacity = int(lines[1].split()[1])
        depots_info = lines[-num_depots:]
        customers_info = lines[num_depots+1:-num_depots]
        return customers_info, depots_info, num_vehicles, num_depots, max_duration_route, vehicle_capacity

