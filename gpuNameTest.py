

def makeNvidiaNames(vendor, series, generations, tiers):

    if vendor == "nvidia":
        for g in generations:
            for t in tiers:
                if g == "10":
                    if int(t) < 80:
                        print(f"{vendor} {series[0]} {g}{t}")
                elif g == '16':
                        if int(t) < 60:
                            print(f"{vendor} {series[0]} {g}{t}")
                elif int(g) > 16 and int(g) < 40:
                    if int(t) > 30 and int(t) < 90:
                        print(f"{vendor} {series[1]} {g}{t}")
                elif int(g) >= 40:
                    if int(t) > 30:
                        print(f"{vendor} {series[1]} {g}{t}")

def makeAMDNames(vendor, series, models):
    for  m in models:
        print(f"{vendor} {series} {m}")
                    
                    
                          





makeNvidiaNames(gpuVendors[0], nvidiaSeries, nvidiaGenerations, nvidiaTiers)
makeAMDNames(gpuVendors[1], AMDSeries[0], radeon5HSeries)
makeAMDNames(gpuVendors[1], AMDSeries[0], radeon5TSeries)
makeAMDNames(gpuVendors[1], AMDSeries[0], radeon6TSeries)
makeAMDNames(gpuVendors[1], AMDSeries[0], radeon7TSeries)