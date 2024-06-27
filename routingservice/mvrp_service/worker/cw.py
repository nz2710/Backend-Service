from mvrp_service.worker.constraint import *
from mvrp_service.utils import *

def isAllRouteIsServed(route_not_served_List):
    for depot_index in range(len(route_not_served_List)):
        if len(route_not_served_List[depot_index]) > 0:
            return False
    return True

def create_savings(depot_index, cluster_index, depots_customers_metadata, X_pairwise, num_depots):
    savings = dict()
    for i in depots_customers_metadata[depot_index][cluster_index]:
        for j in depots_customers_metadata[depot_index][cluster_index]:
            if i != j:            
                max_start = max(i, j)
                min_end = min(i, j)
                key = '[' + str(max_start) + ',' + str(min_end) + ']'
                # savings[key] = D_pairwise[depot_index][i+num_depots] + D_pairwise[depot_index][j+num_depots] - D_pairwise[i+num_depots][j+num_depots]
                savings[key] = distance(X_pairwise[depot_index], X_pairwise[i+num_depots]) + distance(X_pairwise[depot_index], X_pairwise[j+num_depots]) - distance(X_pairwise[i+num_depots], X_pairwise[j+num_depots])
    keys = list(savings.keys())
    values = list(savings.values())
    sorted_value_index = np.argsort(values)
    sorted_value_index = sorted_value_index[::-1]
    sorted_dict_savings = {keys[i]: values[i] for i in sorted_value_index}
    return sorted_dict_savings

# convert '[1, 2]' into list [1, 2]
def get_link(link):
    link = link.replace('[', '')
    link = link.replace(']', '')
    nodes = link.split(',')
    return [int(nodes[0]), int(nodes[1])]

# Kiểm tra xem một nodeI có nằm trong routeR hay không
def is_node_interior(nodeI, routeR):
    try:
        indexNode = routeR.index(nodeI)
        if indexNode == 0 or indexNode == (len(routeR) - 1):
            return False
        return True
    except:
        return False

# Merge 2 route sẵn có với nhau thông qua link (i, j)
def merge(route_r, route_R, link_ij):

    if route_r.index(link_ij[0]) != (len(route_r) - 1):
        route_r.reverse()
    
    if route_R.index(link_ij[1]) != 0:
        route_R.reverse()
        
    return route_r + route_R

# Bước 3 thuật toán
def link_and_route(link, routes):

    node_in_route = [] # mảng lưu danh sách các node thuộc link nếu node này đã được assign vào một route hiện có
    index_route = [-1, -1] # mảng index lưu index route mà các node trong link thuộc vào (nếu có)
    overlap_route = 0 # = 1 nếu cả 2 node trong link đều được assign vào cùng 1 route
    count_node_in_link_belong_exist_route = 0
    
    for route in routes:
        for node in link:
            try:
                route.index(node)
                index_route[count_node_in_link_belong_exist_route] = routes.index(route)
                node_in_route.append(node)
                count_node_in_link_belong_exist_route += 1
            except:
                pass
                
    if index_route[0] == index_route[1]:
        overlap_route = 1
    else:
        overlap_route = 0
        
    return node_in_route, count_node_in_link_belong_exist_route, index_route, overlap_route

def Clark_Wright_Savings_Solver(X_pairwise,
                                savings, 
                                depot_index, 
                                cluster_index, 
                                depots_customers_metadata, 
                                vehicle_capacity,
                                num_depots,
                                max_vehicles,
                                max_duration_route,
                                v
    ):

    routes = [] # mảng lưu lại các route => target của thuật toán

    routes_metada_depot = {}

    # Biến quyết định khi nào dừng thuật toán
    continue_algorithm = True

    # Danh sách các nodes, không bao gồm depot
    node_list = depots_customers_metadata[depot_index][cluster_index]

    # Danh sách các nodes chưa được assign
    node_not_assigneds = []

    # Duyệt lần lượt các link trong mảng savings
    for link in savings.keys():

        if continue_algorithm:

            link = get_link(link)
            node_in_route, count_node_in_link_belong_exist_route, index_route, overlap_route = link_and_route(link, routes)

            # Nếu cả 2 node i, j đều chưa thuộc một route nào => TH1
            if count_node_in_link_belong_exist_route == 0:
                if constrant_total(X_pairwise, link, depot_index, num_depots) <= vehicle_capacity \
                and len(routes) < max_vehicles \
                and time_constranst_total(X_pairwise, link, depot_index, num_depots) <= max_duration_route:
                    routes.append(link)
                    node_list.remove(link[0])
                    node_list.remove(link[1])

            # Nếu một trong hai node i, j thuộc 1 route nào đó sẵn có => TH2    
            elif count_node_in_link_belong_exist_route == 1:

                n_route = node_in_route[0]
                i_route = index_route[0]
                position = routes[i_route].index(n_route)
                link_temp = link.copy()
                link_temp.remove(n_route)
                node = link_temp[0]

                is_node_interior_flag = (not is_node_interior(n_route, routes[i_route]))
                is_satisfy_constrant = (constrant_total(X_pairwise, routes[i_route] + [node], depot_index, num_depots) <= vehicle_capacity)

                if is_node_interior_flag:
                    if is_satisfy_constrant:
                        if position == 0:
                            routes[i_route].insert(0, node)
                            if time_constranst_total(X_pairwise, routes[i_route], depot_index, num_depots) <= max_duration_route:
                                node_list.remove(node)
                            else:
                                routes[i_route] = routes[i_route][1:]
                        else:
                            routes[i_route].append(node)
                            if time_constranst_total(X_pairwise, routes[i_route], depot_index, num_depots) <= max_duration_route:
                                node_list.remove(node)
                            else:
                                routes[i_route] = routes[i_route][:-1]
                    else:
                        continue
                else:
                    continue
            
            # TH3 thuật toán
                    
            else:

                if overlap_route == 0:

                    is_node_interior_flag1 = (not is_node_interior(node_in_route[0], routes[index_route[0]]))
                    is_node_interior_flag2 = (not is_node_interior(node_in_route[1], routes[index_route[1]]))
                    is_satisfy_constrant = (constrant_total(X_pairwise, routes[index_route[0]] + routes[index_route[1]], depot_index, num_depots) <= vehicle_capacity)

                    if is_node_interior_flag1 and is_node_interior_flag2:
                        if is_satisfy_constrant:
                            route_temp = merge(routes[index_route[0]], routes[index_route[1]], node_in_route)
                            if time_constranst_total(X_pairwise, route_temp, depot_index, num_depots) <= max_duration_route:
                                temp1 = routes[index_route[0]]
                                temp2 = routes[index_route[1]]
                                routes.remove(temp1)
                                routes.remove(temp2)
                                routes.append(route_temp)
                                try:
                                    node_list.remove(link[0])
                                    node_list.remove(link[1])
                                except:
                                    pass
                            else:
                                continue
                        else:
                            continue
                    else:
                        continue
                else:
                    continue
        else:
            break

        if len(node_list) > 0:
            continue_algorithm = True
        else:
            continue_algorithm = False

    # Nếu còn bất kỳ node nào chưa được assign route, 
    # tạo 1 route mới chỉ bao gồm 1 node đó (nếu có constranst về số xe, phải kiểm tra thêm điều kiện)
    for node_not_assgined in node_list:
        if 2 * caculate_time_travel(distance(X_pairwise[depot_index], X_pairwise[node_not_assgined + num_depots]), v) <= max_duration_route and len(routes) < max_vehicles:
            routes.append([node_not_assgined])
        else:
            node_not_assigneds.append(node_not_assgined)

    total_distance_route_has_one_node = 0

    # Thêm node depot vào các routes tìm được
    for route in routes:
        route_temp = route
        if len(route_temp) == 1:
            total_distance_route_has_one_node += distance_total(X_pairwise, route_temp, depot_index, num_depots)
        route = ["depot_" + str(depot_index)] + route + ["depot_" + str(depot_index)]
        routes_metada_depot[str(route)] = {
                                            "total_demand": constrant_total(X_pairwise, route, depot_index, num_depots), 
                                            "total_time_serving": time_constranst_total(X_pairwise, route_temp, depot_index, num_depots),
                                            "total_distance": distance_total(X_pairwise, route_temp, depot_index, num_depots),
                                            "total_distance_route_has_one_node": total_distance_route_has_one_node
                                        }

    if len(node_not_assigneds) > 0:
        print("Error: Có một số customers không thoả mãn ràng buộc max_duration_route hoặc max_vehicles !!!")
        return [], {}, node_list

    return routes, routes_metada_depot, node_not_assigneds