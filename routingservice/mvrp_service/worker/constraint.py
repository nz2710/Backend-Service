from mvrp_service.utils import *
from mvrp_service import config
# Tính toán các đại lượng constrant (ở đây đang xét đến demand)
def constrant_total(X_pairwise, route, depot_index, num_depots):
    constrant_total = 0
    for node in route:
        if node == "depot_" + str(depot_index):
            constrant_total += X_pairwise[depot_index][2] # tính demand của node depot_index
        else:
            constrant_total += X_pairwise[node+num_depots][2] # tính demand của node customers
    return constrant_total

# Time serving constranst
def time_constranst_total(X_pairwise, routeNew, depot_index, num_depots):
    total_time = 0.0
    routeNewTemp = routeNew
    if routeNew[0] == depot_index and routeNew[-1] == depot_index and len(routeNew) > 1:
        routeNewTemp = routeNew[1:-1]
    for i in range(len(routeNewTemp)):
        if routeNewTemp[i] != depot_index and i + 1 <= len(routeNewTemp) - 1:
            # total_time += T_pairwise[routeNewTemp[i] + num_depots][routeNewTemp[i + 1] + num_depots] + X_pairwise[routeNewTemp[i] + num_depots][3]
            total_time += caculate_time_travel(distance(X_pairwise[routeNewTemp[i] + num_depots], X_pairwise[routeNewTemp[i + 1] + num_depots]), config.V) + X_pairwise[routeNewTemp[i] + num_depots][3]
        else:
            total_time += X_pairwise[routeNewTemp[i] + num_depots][3]
    # total_time += T_pairwise[depot_index][routeNewTemp[0] + num_depots]
    total_time += caculate_time_travel(distance(X_pairwise[depot_index], X_pairwise[routeNewTemp[0] + num_depots]), config.V)
    # total_time += T_pairwise[depot_index][routeNewTemp[-1] + num_depots]
    total_time += caculate_time_travel(distance(X_pairwise[depot_index], X_pairwise[routeNewTemp[-1] + num_depots]), config.V)
    return total_time

# Phân bổ xe cho các route chưa được serving cùng 1 depot
# Serving cho các route có distance total từ nhỏ nhất đến lớn nhất đến khi time last (thời gian còn lại) của vehicle không còn thoả mãn
def allocateVehiclePerDepot(X_pairwise, depot_routes_sorted_time, depot_index, num_depots, max_duration_route, num_vehicle_assigned_depot):

    timeLastVehicleDict = {}
    timeRouteDict = {}
    flag_used_route = {}
    route_served = []
    route_not_served = []

    for i, r in enumerate(range(len(depot_routes_sorted_time))):
        timeRouteDict[r] = time_constranst_total(X_pairwise, depot_routes_sorted_time[i], depot_index, num_depots)
        flag_used_route[r] = False
    
    for i, v in enumerate(range(len(depot_routes_sorted_time))):
        t = time_constranst_total(X_pairwise, depot_routes_sorted_time[i], depot_index, num_depots)
        if max_duration_route >= t:
            timeLastVehicleDict[v] = max_duration_route - t
            if i < num_vehicle_assigned_depot:
                flag_used_route[v] = True
        else:
            timeLastVehicleDict[v] = max_duration_route

    finish = False
    count = 0

    while finish != True:

        # print("==== Allocate vehicle iter " + str(count) + " ====")

        finish = True

        list_route_used = []
        list_route_not_used = []

        route_served = []
        route_not_served = []

        for i, r in enumerate(range(len(depot_routes_sorted_time))):
            if flag_used_route[r]:
                list_route_used.append(r)
                # print("Route " + str(r) + " is served has total time: " + str(timeRouteDict[r]))
                route_served.append(depot_routes_sorted_time[r])
            else:
                list_route_not_used.append(r)
                # print("Route " + str(r) + " is not served has total time: " + str(timeRouteDict[r]))
                route_not_served.append(depot_routes_sorted_time[r])

        # for i, v in enumerate(range(num_vehicle_assigned_depot)):
        #     print("Time left for vehicle " + str(v) + ": " + str(timeLastVehicleDict[v]))
        
        for route_used in list_route_used:
            for route_not_used in list_route_not_used:
                if 0 < timeRouteDict[route_not_used] <= timeLastVehicleDict[route_used] and flag_used_route[route_not_used] != True:
                    timeLastVehicleDict[route_used] = timeLastVehicleDict[route_used] - timeRouteDict[route_not_used]
                    flag_used_route[route_not_used] = True
                    finish = False

        count = count + 1

        return timeLastVehicleDict, route_served, route_not_served

# Phân bổ xe cho các route chưa được serving trong cùng một depot, sử dụng các xe lấy từ depot gần nhất
def allocateVehiclePerDepotAfter(X_pairwise, depot_routes_sorted_time, depot_index, num_depots, timeLastVehicleDictCloset, flag_assigned):

    timeRouteDict = {}
    flag_used_route = {}
    route_not_served = []
    route_served = []

    for i, r in enumerate(range(len(depot_routes_sorted_time))):
        timeRouteDict[r] = time_constranst_total(X_pairwise, depot_routes_sorted_time[i], depot_index, num_depots)
        flag_used_route[r] = False

    for i, r in enumerate(range(len(depot_routes_sorted_time))):
        t = timeRouteDict[r]
        for v in timeLastVehicleDictCloset.keys():
            if timeLastVehicleDictCloset[v] >= t and flag_assigned[depot_index][v] is False:
                timeLastVehicleDictCloset[v] = timeLastVehicleDictCloset[v] - t
                flag_used_route[i] = True
                flag_assigned[depot_index][v] = True
                break

    finish = False
    count = 0

    while finish != True:

        finish = True

        list_route_not_used = []
        route_served = []
        route_not_served = []

        for i, r in enumerate(range(len(depot_routes_sorted_time))):
            if not flag_used_route[r]:
                list_route_not_used.append(r) 
                route_not_served.append(depot_routes_sorted_time[r])
            else:
                route_served.append(depot_routes_sorted_time[r])
        
        for route_not_used in list_route_not_used:
            for v in timeLastVehicleDictCloset.keys():
                if timeLastVehicleDictCloset[v] >= timeRouteDict[route_not_used] and flag_used_route[route_not_used] != True and flag_assigned[depot_index][v] is False:
                    timeLastVehicleDictCloset[v] = timeLastVehicleDictCloset[v] - timeRouteDict[route_not_used]
                    flag_used_route[route_not_used] = True
                    flag_assigned[depot_index][v] = True
                    finish = False
                    break

        count = count + 1

        return timeLastVehicleDictCloset, route_served, route_not_served