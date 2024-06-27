import math
import numpy as np
from tqdm import tqdm
from fractions import Fraction
from mvrp_service import config
from haversine import haversine

def diffDict(dict1, dict2):
    list_diff_key = []
    for key1 in dict1.keys():
        if dict1[key1] == dict2[key1]:
            continue
        else:
            list_diff_key.append(key1)
    return list_diff_key

# Hàm tính khoảng cách eclid
def distanceEuclid(node1, node2):
    square = math.pow((node1[0] - node2[0]), 2) + math.pow((node1[1] - node2[1]), 2)
    return math.sqrt(square)

# Hàm tính khoảng cách eclid
def distance(node1, node2):
    return haversine((node1[0], node1[1]), (node2[0], node2[1]))
    # square = math.pow((node1[0] - node2[0]), 2) + math.pow((node1[1] - node2[1]), 2)
    # return math.sqrt(square)

# Total distance
def distance_total(X_pairwise, routeNew, depot_index, num_depots):
    total_distance = 0.0
    routeNewTemp = routeNew
    if routeNew[0] == depot_index and routeNew[-1] == depot_index and len(routeNew) > 1:
        routeNewTemp = routeNew[1:-1]
    for i in range(len(routeNewTemp) - 1):
        if routeNewTemp[i] != depot_index:
            total_distance += distance(X_pairwise[routeNewTemp[i] + num_depots], X_pairwise[routeNewTemp[i + 1] + num_depots])
    total_distance += distance(X_pairwise[depot_index], X_pairwise[routeNewTemp[0] + num_depots])
    total_distance += distance(X_pairwise[depot_index], X_pairwise[routeNewTemp[-1] + num_depots])
    return total_distance

# Time serving constranst for sorted
def time_constranst_total_for_sort(X_pairwise, routeNew, depot_index, num_depots):
    total_time = 0.0
    routeNewTemp = routeNew
    if routeNew[0] == depot_index and routeNew[-1] == depot_index and len(routeNew) > 1:
        routeNewTemp = routeNew[1:-1]
    for i in range(len(routeNewTemp)):
        if routeNewTemp[i] != depot_index and i + 1 <= len(routeNewTemp) - 1:
            total_time += caculate_time_travel(distance(X_pairwise[routeNewTemp[i] + num_depots], X_pairwise[routeNewTemp[i + 1] + num_depots]), config.V) + X_pairwise[routeNewTemp[i] + num_depots][3]
        else:
            total_time += X_pairwise[routeNewTemp[i] + num_depots][3]
    return total_time

# Total distance for sorted
def distance_total_for_sort(X_pairwise, routeNew, depot_index, num_depots):
    total_distance = 0.0
    routeNewTemp = routeNew
    if routeNew[0] == depot_index and routeNew[-1] == depot_index and len(routeNew) > 1:
        routeNewTemp = routeNew[1:-1]
    for i in range(len(routeNewTemp) - 1):
        if routeNewTemp[i] != depot_index:
            total_distance += distance(X_pairwise[routeNewTemp[i] + num_depots], X_pairwise[routeNewTemp[i + 1] + num_depots])
    return total_distance

# Hàm tính thời gian
def caculate_time_travel(distance, v):
    return (distance / v) * 60

def caculate_pairwise(customers_info, depots_info, v):

    X_pairwise = []
    X_for_kmean = []
    De_for_kmean = []
    ID_mappings = {}

    for i, depot_info in enumerate(depots_info):
        depot_info = " ".join(depot_info.split())
        x = float(depot_info.split()[1])
        y = float(depot_info.split()[2])
        d = float(depot_info.split()[4])
        t = float(depot_info.split()[3])
        id = int(depot_info.split()[5])
        De_for_kmean.append([x, y])
        X_pairwise.append([x, y, d, t])
        ID_mappings[i] = id

    De_for_kmean = np.array(De_for_kmean)
    
    for i, customer_info in enumerate(customers_info):
        customer_info = " ".join(customer_info.split())
        x = float(customer_info.split()[1])
        y = float(customer_info.split()[2])
        d = float(customer_info.split()[4])
        t = float(customer_info.split()[3])
        id = int(customer_info.split()[5])
        X_for_kmean.append([x, y])
        X_pairwise.append([x, y, d, t])
        ID_mappings[i + len(depots_info)] = id

    X_for_kmean = np.array(X_for_kmean)

    # num_nodes = len(X_pairwise)

    # D_pairwise = np.zeros((num_nodes, num_nodes), dtype=np.float16)

    # T_pairwise = np.zeros((num_nodes, num_nodes), dtype=np.float16)

    # for i in tqdm(range(num_nodes)):
    #     for j in range(num_nodes):
    #         if i == j:
    #             D_pairwise[i][j] = 0.0
    #             T_pairwise[i][j] = 0.0
    #         else:
    #             D_pairwise[i][j] = distance(X_pairwise[i], X_pairwise[j])
    #             T_pairwise[i][j] = caculate_time_travel(D_pairwise[i][j], v)
    
    return X_for_kmean, De_for_kmean, X_pairwise, ID_mappings

def exist_vehicle_not_used(flag_assigned, depot_index):
    for key in flag_assigned[depot_index].keys():
        if flag_assigned[depot_index][key] is False:
            return True
    return False

# Lấy depot gần nhất với depot đang xét, bao gồm check điều kiện các xe có sẵn hay ko
def get_depot_closet(depot_index, num_depots, X_pairwise, flag_assigned):
    min_distance = config.MAX_NUMBER
    for di in range(num_depots):
        if di != depot_index and exist_vehicle_not_used(flag_assigned, depot_index):
            min_distance = min(min_distance, distance(X_pairwise[di], X_pairwise[depot_index]))
    for di in range(num_depots):
        if di != depot_index and exist_vehicle_not_used(flag_assigned, depot_index):
            if min_distance == distance(X_pairwise[di], X_pairwise[depot_index]):
                return di
    return -1

def get_depot_closet_not_related_vehicle(depot_index, num_depots, X_pairwise, flag_assigned):
    min_distance = config.MAX_NUMBER
    for di in range(num_depots):
        if di != depot_index and flag_assigned[di] != True:
            min_distance = min(min_distance, distance(X_pairwise[di], X_pairwise[depot_index]))
    for di in range(num_depots):
        if di != depot_index and flag_assigned[di] != True:
            if min_distance == distance(X_pairwise[di], X_pairwise[depot_index]):
                return di
    return -1

def caculate_vehicles_for_each_depot_method_1(X_pairwise, 
                                     num_vehicles, 
                                     num_vehicle_depots_metadata_sorted, 
                                     num_vehicle_depots_metadata_sorted_origin
                                     ):
    num_depots = len(num_vehicle_depots_metadata_sorted)
    fraction = Fraction(num_vehicles, num_depots)
    simplified_fraction = str(fraction.limit_denominator())
    if len(simplified_fraction.split("/")) == 1:
        a = int(num_vehicles / num_depots)
        b = 1
    else:
        a = int(simplified_fraction.split("/")[0])
        b = int(simplified_fraction.split("/")[1])
    t = 0 # số lượng vehicles được chia đều cho mỗi depot
    if a > b:
        t = math.ceil(a / b) # 5 / 3 => t = 2
    else:
        if a == num_vehicles:
            t = 1
        else:
            t = b - (a % b)
    num_vehicle_assign_last = num_vehicles
    flag_assigned = {} # dictionary kiểm tra xe depot i đã được assign vehicle hay chưa
    for i, _ in enumerate(num_vehicle_depots_metadata_sorted):
        flag_assigned[num_vehicle_depots_metadata_sorted[i][0]] = False
    for i, _ in enumerate(num_vehicle_depots_metadata_sorted):
        if num_vehicle_assign_last >= t:
            if i > 0:
                # if i == 3:
                #     print(str(i) + " === " + str(num_vehicle_assign_last))
                # Từ depot ở vị trí i + 1 trong mảng num_vehicle_depots_metadata_sorted trở đi
                # Tìm depot gần nhất với depot ở vị trí i
                depot_closet_i = get_depot_closet_not_related_vehicle(num_vehicle_depots_metadata_sorted[i][0], num_depots, X_pairwise, flag_assigned)
                # if i == 3:
                #     print("depot_closet: " + str(depot_closet_i))
                if depot_closet_i != -1:
                    if flag_assigned[depot_closet_i] == False:
                        for index, item in enumerate(num_vehicle_depots_metadata_sorted):
                            if item[0] == depot_closet_i:
                                if num_vehicle_depots_metadata_sorted[index][1] >= t:
                                    num_vehicle_depots_metadata_sorted[index] = (num_vehicle_depots_metadata_sorted[index][0], t)
                                    num_vehicle_assign_last = num_vehicle_assign_last - t
                                else:
                                    num_vehicle_assign_last = num_vehicle_assign_last - num_vehicle_depots_metadata_sorted[index][1]
                                flag_assigned[depot_closet_i] = True
                                break
                else:
                    if num_vehicle_depots_metadata_sorted[i][1] >= t:
                        num_vehicle_depots_metadata_sorted[i] = (num_vehicle_depots_metadata_sorted[i][0], num_vehicle_assign_last)
                        # if i == 3:
                        #     print("depot " + str(num_vehicle_depots_metadata_sorted[i][0]) + ": " + str(num_vehicle_assign_last))
                        num_vehicle_assign_last = 0
                    else:
                        num_vehicle_assign_last = num_vehicle_assign_last - num_vehicle_depots_metadata_sorted[i][1]
                    flag_assigned[num_vehicle_depots_metadata_sorted[i][0]] = True
            else:
                if flag_assigned[num_vehicle_depots_metadata_sorted[i][0]] == False:
                    if num_vehicle_depots_metadata_sorted[i][1] >= t:
                        num_vehicle_depots_metadata_sorted[i] = (num_vehicle_depots_metadata_sorted[i][0], t)
                        num_vehicle_assign_last = num_vehicle_assign_last - t
                    else:
                        num_vehicle_assign_last = num_vehicle_assign_last - num_vehicle_depots_metadata_sorted[i][1]
                    flag_assigned[num_vehicle_depots_metadata_sorted[i][0]] = True
        elif 0 < num_vehicle_assign_last < t:
            # Từ depot ở vị trí i + 1 trong mảng num_vehicle_depots_metadata_sorted trở đi
            # Tìm depot gần nhất với depot ở vị trí i
            depot_closet_i = get_depot_closet_not_related_vehicle(num_vehicle_depots_metadata_sorted[i][0], num_depots, X_pairwise, flag_assigned)
            if depot_closet_i != -1:
                if flag_assigned[depot_closet_i] == False:
                    for index, item in enumerate(num_vehicle_depots_metadata_sorted):
                        if item[0] == depot_closet_i:
                            if num_vehicle_depots_metadata_sorted[index][1] >= num_vehicle_assign_last:
                                num_vehicle_depots_metadata_sorted[index] = (num_vehicle_depots_metadata_sorted[index][0], num_vehicle_assign_last)
                                num_vehicle_assign_last = 0
                            else:
                                num_vehicle_assign_last = num_vehicle_assign_last - num_vehicle_depots_metadata_sorted[index][1]
                            flag_assigned[depot_closet_i] = True
                            break
        else:
            if flag_assigned[num_vehicle_depots_metadata_sorted[i][0]] == False:
                num_vehicle_depots_metadata_sorted[i] = (num_vehicle_depots_metadata_sorted[i][0], 0)
                flag_assigned[num_vehicle_depots_metadata_sorted[i][0]] = True
    # print(num_vehicle_assign_last)
    # Những depot_index chưa được xét đến => gán số vehicles = 0
    for i, _ in enumerate(num_vehicle_depots_metadata_sorted):
        if flag_assigned[num_vehicle_depots_metadata_sorted[i][0]] == False:
            num_vehicle_depots_metadata_sorted[i] = (num_vehicle_depots_metadata_sorted[i][0], 0)
            flag_assigned[num_vehicle_depots_metadata_sorted[i][0]] = True
    # Nếu vẫn còn dư 1 số lượng vehicles, thêm dần vào từng depot đã được duyệt theo thứ tự trong mảng
    for i, _ in enumerate(num_vehicle_depots_metadata_sorted_origin.keys()):
        if flag_assigned[num_vehicle_depots_metadata_sorted[i][0]] == True:
            while num_vehicle_depots_metadata_sorted[i][1] < num_vehicle_depots_metadata_sorted_origin[num_vehicle_depots_metadata_sorted[i][0]] and num_vehicle_assign_last > 0:
                num_vehicle_depots_metadata_sorted[i] = (num_vehicle_depots_metadata_sorted[i][0], num_vehicle_depots_metadata_sorted[i][1] + 1)
                num_vehicle_assign_last = num_vehicle_assign_last - 1
    return num_vehicle_depots_metadata_sorted