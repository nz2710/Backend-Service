import numpy as np
from mvrp_service.worker.kmeanClustering import KmeanMVRP
from mvrp_service import config
from mvrp_service.utils import *
from mvrp_service.worker.constraint import *
from mvrp_service.worker.cw import *
from mvrp_service.dataParser import prParser

np.random.seed(config.SEED_NUMBER)

# Time đơn vị tính theo phút
# Distance đơn vị tính theo km
# Vận tốc v tính theo km/h

# num_vehicles = 70
# max_duration_route = 500

def mvrp(file_path, V):

    customers_info, depots_info, num_vehicles, num_depots, max_duration_route, vehicle_capacity = prParser(file_path)

    max_vehicles = config.MAX_NUMBER

    X_for_kmean, De_for_kmean, X_pairwise, ID_mappings = caculate_pairwise(customers_info, depots_info, v=config.V)

    v = V

    # Clustering
    app = KmeanMVRP()

    _, _, depots_customers_metadata = app.kmean_assign_depots_closest_centroids(
                                                                                        X=X_for_kmean, 
                                                                                        De=De_for_kmean, 
                                                                                        num_depots=num_depots, 
                                                                                        num_clusters=num_depots
                                                                                    )

    total_distance_without_allocating_vehicles = 0.0
    total_demand_without_allocating_vehicles = 0.0
    total_time_serving_without_allocating_vehicles = 0.0
    total_vehicle_used = 0
    num_vehicle_depots_metadata = {} # mảng lưu lại thông tin số lượng xe tương ứng với từng depot
    alternative_dict = {}
    depot_origin = {}
    routes_metada_depot_list = []

    # Tối ưu route, tính toán chi phí cho tất cả các depot
    for depot_index in range(num_depots):
        savings = create_savings(depot_index, 0, depots_customers_metadata, X_pairwise, num_depots)
        _, routes_metada_depot, _ = Clark_Wright_Savings_Solver(
                                    X_pairwise, 
                                    savings, 
                                    depot_index, 
                                    0, 
                                    depots_customers_metadata, 
                                    vehicle_capacity,
                                    num_depots,
                                    max_vehicles,
                                    max_duration_route,
                                    v
                                )
        
        for route in routes_metada_depot.keys():
            total_distance_without_allocating_vehicles += routes_metada_depot[route]["total_distance"]
            total_demand_without_allocating_vehicles += routes_metada_depot[route]["total_demand"]
            total_time_serving_without_allocating_vehicles += routes_metada_depot[route]["total_time_serving"]
        
        routes_metada_depot_list.append(routes_metada_depot)
        num_vehicle_depots_metadata[depot_index] = len(routes_metada_depot.keys()) # số lượng route trong 1 depot

    # print("************************************************************")
    # print("         Final Results Without Allocating Vehicles")
    # print("************************************************************")
    # print("Total Demand: " + str(total_demand))
    # print("Total Distance: " + str(total_distance) + " km")
    # print("Total Time_serving: " + str(total_time_serving) + " minutes = " + str(total_time_serving / 60) + " hours")

    total_distance_served = 0.0
    total_demand_served = 0.0
    total_time_serving_served = 0.0

    # Sort num_vehicle_depots_metadata follow by num of routes
    num_vehicle_depots_metadata_sorted = sorted(num_vehicle_depots_metadata.items(), key=lambda x:x[1], reverse=True)

    num_vehicle_depots_metadata_sorted_origin = {}

    for item in num_vehicle_depots_metadata_sorted:
        num_vehicle_depots_metadata_sorted_origin[item[0]] = item[1]

    # print("Num_depot: " + str(num_depots))
    # print("Num vehicles: " + str(num_vehicles))

    # print("--------- Số xe ước tính ---------") 
    # # Tuple (a, b) => a là depot_index, b là số xe tương ứng
    # print(num_vehicle_depots_metadata_sorted)

    num_vehicle_depots_metadata_reassigned = caculate_vehicles_for_each_depot_method_1(X_pairwise, num_vehicles, num_vehicle_depots_metadata_sorted, num_vehicle_depots_metadata_sorted_origin)

    # print("--------- Số xe thực tế được phân chia ---------") 
    # # Tuple (a, b) => a là depot_index, b là số xe tương ứng
    # print(num_vehicle_depots_metadata_reassigned)

    for item in num_vehicle_depots_metadata_reassigned:
        total_vehicle_used += item[1]

    timeLastVehicleDictList = []
    route_not_served_List = []
    route_not_served_List_return = []
    route_served_List = []
    route_served_List_return = []

    for depot_index in range(num_depots):

        depot_routes_sorted_time = []

        for route in list(routes_metada_depot_list[depot_index].keys()):
            depot_routes_sorted_time.append(eval(route)[1:-1])

        depot_routes_sorted_time = sorted(depot_routes_sorted_time, key=lambda x:time_constranst_total_for_sort(X_pairwise, x, depot_index, num_depots), reverse=True)

        # print("==== List route of depot " + str(depot_index) + " =====")
        # for route in depot_routes_sorted_time:
        #     print(route)

        for item in num_vehicle_depots_metadata_reassigned:
            if item[0] == depot_index:
                num_vehicle_assigned_depot = item[1]

        timeLastVehicleDict, route_served, route_not_served = allocateVehiclePerDepot(X_pairwise, depot_routes_sorted_time, depot_index, num_depots, max_duration_route, num_vehicle_assigned_depot)

        # for item in timeLastVehicleDict.keys():
        #     if timeLastVehicleDict[item] < max_duration_route:
        #         total_vehicle_used += 1

        timeLastVehicleDictList.append(timeLastVehicleDict)
        route_not_served_List.append(route_not_served)
        route_served_List.append(route_served)

    # Tính toán chi phí cho các route đã được serving
    for depot_index, route_served in enumerate(route_served_List):
        for route in route_served:
            route = ["depot_" + str(depot_index)] + route + ["depot_" + str(depot_index)]
            for route_temp in routes_metada_depot_list[depot_index].keys():
                if str(route) == str(route_temp):
                    total_distance_served += routes_metada_depot_list[depot_index][str(route)]["total_distance"]
                    total_demand_served += routes_metada_depot_list[depot_index][str(route)]["total_demand"]
                    total_time_serving_served += routes_metada_depot_list[depot_index][str(route)]["total_time_serving"]
                    route_served_List_return.append(route)
                    alternative_dict[str(route)] = False
                    depot_origin[str(route)] = "depot_" + str(depot_index)

    # Đánh dấu xem xe depot nào đang được sử dụng
    flag_assigned = {}
    for depot_index in range(num_depots):
        flag_assigned[depot_index] = False

    closet_depot_info = []
    for depot_index in range(num_depots):
        depot_index_closest = get_depot_closet_not_related_vehicle(depot_index, num_depots, X_pairwise, flag_assigned)
        closet_depot_info.append([depot_index, depot_index_closest, distance(X_pairwise[depot_index], X_pairwise[depot_index_closest])])

    # sort by distance
    closet_depot_info = sorted(closet_depot_info, key=lambda x:x[2])

    # print("--------- Closet depot mapping ---------")
    # print(closet_depot_info)

    # print("************************************************************")
    # print("          After First Phase Routing Of all Depot")
    # print("************************************************************")

    # total_distance_not_served = 0

    # for depot_index in range(num_depots):
    #     print("---- Depot " + str(depot_index) + " ----")
    #     print("Vehicles Available:")
    #     print(timeLastVehicleDictList[depot_index])
    #     print("Routes Not Serving:")
    #     print(route_not_served_List[depot_index])
    #     for route in route_not_served_List[depot_index]:
    #         total_distance_not_served += distance_total(D_pairwise, route, depot_index, num_depots)

    # print("Total Distance Served: " + str(total_distance) + " km")
    # print("Total Distance not Served: " + str(total_distance_not_served) + " km")
    # print("Total Distance All: " + str(total_distance_not_served + total_distance) + " km")

    # print("************************************************************")
    # print(" Move Vehicles Available from Depot Closet to Depot Current")
    # print("************************************************************")

    # Đánh dấu xem xe trong depot nào đang được sử dụng
    flag_assigned = {}
    for depot_index in range(num_depots):
        flag_assigned[depot_index] = {}
        for vehicle_index in timeLastVehicleDictList[depot_index].keys():
            flag_assigned[depot_index][vehicle_index] = False

    for item in closet_depot_info:

        depot_index = item[0]

        if len(route_not_served_List[depot_index]) > 0:

            flag_assigned2 = {}
            for depot_index in range(num_depots):
                flag_assigned2[depot_index] = False

            # Tìm depot gần nhất với depot hiện tại
            depot_index_closest = get_depot_closet(depot_index, num_depots, X_pairwise, flag_assigned)

            list_depots_iter = []

            depot_index_closest_not_relate_vehicles = get_depot_closet_not_related_vehicle(depot_index, num_depots, X_pairwise, flag_assigned2)

            while depot_index_closest_not_relate_vehicles != -1:
                flag_assigned2[depot_index_closest_not_relate_vehicles] = True
                list_depots_iter.append(depot_index_closest_not_relate_vehicles)
                depot_index_closest_not_relate_vehicles = get_depot_closet_not_related_vehicle(depot_index, num_depots, X_pairwise, flag_assigned2)

            if depot_index_closest != -1:
                list_depots_iter.remove(depot_index_closest)
                list_depots_iter = [depot_index_closest] + list_depots_iter

            # print(list_depots_iter)

            # flag_assigned[depot_index] = True
            # flag_assigned[depot_index_closest] = True

            for depot_index_closest in list_depots_iter:

                # list_vehicle_in_depot = timeLastVehicleDictList[depot_index]
                list_vehicle_in_depot_closest = timeLastVehicleDictList[depot_index_closest]

                # Sort vehicle by time last
                keys = list(list_vehicle_in_depot_closest.keys())
                values = list(list_vehicle_in_depot_closest.values())
                sorted_value_index = np.argsort(values)
                list_vehicle_in_depot_closest_sorted = {keys[i]: values[i] for i in sorted_value_index}

                # Sort route by distance total
                route_not_served_List_sorted_time = sorted(route_not_served_List[depot_index], key=lambda x:time_constranst_total_for_sort(X_pairwise, x, depot_index, num_depots), reverse=True)
                
                timeLastVehicleDictCloset, route_served_after, route_not_served_after = allocateVehiclePerDepotAfter(X_pairwise, route_not_served_List_sorted_time, depot_index_closest, num_depots, list_vehicle_in_depot_closest_sorted, flag_assigned)

                # print(flag_assigned)

                # for item in timeLastVehicleDictCloset.keys():
                #     if timeLastVehicleDictCloset[item] < max_duration_route:
                #         total_vehicle_used += 1

                # Tính toán chi phí routing
                for route in route_served_after:
                    route_temp = ["depot_" + str(depot_index_closest)] + route + ["depot_" + str(depot_index_closest)]
                    route = [depot_index_closest] + route + [depot_index_closest]
                    total_distance_served += distance_total(X_pairwise, route, depot_index_closest, num_depots)
                    total_demand_served += constrant_total(X_pairwise, route[1:-1], depot_index_closest, num_depots)
                    total_time_serving_served += time_constranst_total(X_pairwise, route, depot_index_closest, num_depots)
                    route_served_List_return.append(route_temp)
                    alternative_dict[str(route_temp)] = True
                    depot_origin[str(route_temp)] = "depot_" + str(depot_index)

                # print("Routes not serving in Depot " + str(depot_index) + ":")
                # print(route_not_served_List[depot_index])
                # print("Vehicles Available From Depot " + str(depot_index) + ":")
                # print(timeLastVehicleDictList[depot_index])
                # print("Vehicles Available From Depot Closet " + str(depot_index_closest) + ":")
                # print(timeLastVehicleDictList[depot_index_closest])

                # Tính toán chi phí luân chuyển vehicles
                # Có bao nhiêu key thay đổi thì có bây nhiêu xe được luân chuyển từ depot_index_closest đến depot_index
                # list_diff_key = diffDict(list_vehicle_in_depot_closest, list_vehicle_in_depot_closest_sorted)
                # for key in list_diff_key:
                #     total_distance_served+= D_pairwise[depot_index][depot_index_closest] # khoảng cách từ depot i đến depot j
                #     total_time_serving_served+= caculate_time_travel(D_pairwise[depot_index][depot_index_closest], v)
                #     del list_vehicle_in_depot_closest[key]

                # print("Vehicles Available From Depot Closet " + str(depot_index_closest) + " After Routing:")
                # print(list_vehicle_in_depot_closest_sorted)
                # print("Routes not serving After Routing:")
                # print(route_not_served_after)

                timeLastVehicleDictList[depot_index_closest] = list_vehicle_in_depot_closest_sorted # cập nhật time last của các xe ở depot gần nhất
                route_not_served_List[depot_index] = route_not_served_after # cập nhật thông tin các route chưa được serving tại depot_index

                # depot_index_closest = get_depot_closet(depot_index, num_depots, X_pairwise, flag_assigned)

                # print("************************************************************")
                # print("************************************************************")
        else:
            continue
            # print("************************************************************")
            # print("************************************************************")
            # print("No need for allocating vehicle, all routes in depot " + str(depot_index) + " are served !!!")
            # print("************************************************************")
            # print("************************************************************")

    # print()
    # print("************************************************************")
    # print("          After Two Phase Routing Of all Depot")
    # print("************************************************************")

    print(flag_assigned)

    for depot_index, route_not_served in enumerate(route_not_served_List):
        if len(route_not_served) > 0:
            for route in route_not_served:
                route = ["depot_" + str(depot_index)] + route + ["depot_" + str(depot_index)]
                route_not_served_List_return.append(route)
                alternative_dict[str(route)] = False
                depot_origin[str(route)] = "depot_" + str(depot_index)
    
    # count_route_not_serving = 0
    total_distance_not_served = 0

    for depot_index in range(num_depots):
        # print("---- Depot " + str(depot_index) + " ----")
        # print("Vehicles Available:")
        # print(timeLastVehicleDictList[depot_index])
        # print("Routes Not Serving:")
        # if len(route_not_served_List[depot_index]) > 0:
        #     count_route_not_serving += 1
        # print(route_not_served_List[depot_index])
        for route in route_not_served_List[depot_index]:
            total_distance_not_served += distance_total(X_pairwise, route, depot_index, num_depots)

    # print("************************************************************")
    # print("                      Final Results")
    # print("************************************************************")
    # print("Total Demand: " + str(total_demand_served))
    # print("Total Distance Served: " + str(total_distance_served) + " km")
    # print("Total Distance not Served: " + str(total_distance_not_served) + " km")
    # print("Total Distance All: " + str(total_distance_not_served + total_distance_served) + " km")
    # print("Total Time_serving: " + str(total_time_serving_served) + " minutes = " + str(total_time_serving_served / 60) + " hours")

    route_served_List_return_ids, route_not_served_List_return_ids  = [], []

    # print(ID_mappings)

    for route in route_served_List_return:
        route_list_ids = []
        depot_index = 0
        for node in route:
            if str(node).__contains__("depot"):
                depot_index = int(node.split("_")[-1])
                route_list_ids.append("depot_" + str(ID_mappings[int(node.split("_")[-1])]))
            else:
                route_list_ids.append(ID_mappings[node + num_depots])
        route_list_ids_metadata = {}
        total_demand = constrant_total(X_pairwise, route[1:-1], depot_index, num_depots)
        total_time_serving = time_constranst_total(X_pairwise, [depot_index] + route[1:-1] + [depot_index], depot_index, num_depots)
        total_distance = distance_total(X_pairwise, [depot_index] + route[1:-1] + [depot_index], depot_index, num_depots)
        route_list_ids_metadata["total_demand"] = total_demand
        route_list_ids_metadata["total_time_serving"] = total_time_serving
        route_list_ids_metadata["total_distance"] = total_distance
        route_list_ids_metadata["route"] = route_list_ids
        route_list_ids_metadata["alternative"] = alternative_dict[str(route)]
        route_list_ids_metadata["depot_origin"] = "depot_" + str(ID_mappings[int(depot_origin[str(route)].split("_")[-1])])
        route_served_List_return_ids.append(route_list_ids_metadata)

    for route in route_not_served_List_return:
        route_list_ids = []
        depot_index = 0
        for node in route:
            if str(node).__contains__("depot"):
                depot_index = int(node.split("_")[-1])
                route_list_ids.append("depot_" + str(ID_mappings[int(node.split("_")[-1])]))
            else:
                route_list_ids.append(ID_mappings[node + num_depots])
        route_list_ids_metadata = {}
        total_demand = constrant_total(X_pairwise, route[1:-1], depot_index, num_depots)
        total_time_serving = time_constranst_total(X_pairwise, [depot_index] + route[1:-1] + [depot_index], depot_index, num_depots)
        total_distance = distance_total(X_pairwise, [depot_index] + route[1:-1] + [depot_index], depot_index, num_depots)
        route_list_ids_metadata["total_demand"] = total_demand
        route_list_ids_metadata["total_time_serving"] = total_time_serving
        route_list_ids_metadata["total_distance"] = total_distance
        route_list_ids_metadata["route"] = route_list_ids
        route_list_ids_metadata["alternative"] = alternative_dict[str(route)]
        route_list_ids_metadata["depot_origin"] = "depot_" + str(ID_mappings[int(depot_origin[str(route)].split("_")[-1])])
        route_not_served_List_return_ids.append(route_list_ids_metadata)

    total_num_customer_served = 0
    for item in route_served_List_return_ids:
        total_num_customer_served += len(item["route"][1:-1])

    total_num_customer_not_served = 0
    for item in route_not_served_List_return_ids:
        total_num_customer_not_served += len(item["route"][1:-1])

    return total_distance_without_allocating_vehicles, total_demand_without_allocating_vehicles, \
            total_time_serving_without_allocating_vehicles, route_served_List_return, \
            route_not_served_List_return, route_served_List_return_ids, route_not_served_List_return_ids, \
            total_num_customer_served, total_num_customer_not_served, \
            total_demand_served, total_distance_served, total_time_serving_served, total_vehicle_used, num_vehicles

# total_distance_without_allocating_vehicles, total_demand_without_allocating_vehicles, total_time_serving_without_allocating_vehicles, route_served_List_return, route_not_served_List_return, total_demand_served, total_distance_served, total_time_serving_served = mvrp(customers_info, depots_info, num_vehicles, num_depots, max_duration_route, vehicle_capacity)